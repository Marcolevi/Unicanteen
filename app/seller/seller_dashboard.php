<?php
require_once __DIR__ . '/../includes/common_footer.php';
session_start();
require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../services/EmailNotificationService.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: /Ordering/app/auth/login_register.php");
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];

/* CLEAR COMPLETED / CANCELLED */
function hasOrderColumn($conn, $column) {
    static $cache = [];
    if (!array_key_exists($column, $cache)) {
        $escaped = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$escaped'");
        $cache[$column] = $result && $result->num_rows > 0;
    }
    return $cache[$column];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_completed'])) {
        if (hasOrderColumn($conn, 'seller_archived')) {
            $stmt = $conn->prepare("UPDATE orders SET seller_archived=1 WHERE seller_id=? AND status='completed'");
        } else {
            $stmt = $conn->prepare("DELETE FROM orders WHERE seller_id=? AND status='completed'");
        }
        $stmt->bind_param("i",$seller_id);
        $stmt->execute();
        header("Location:/Ordering/app/seller/seller_dashboard.php"); exit;
    }
    if (isset($_POST['clear_cancelled'])) {
        if (hasOrderColumn($conn, 'seller_archived')) {
            $stmt = $conn->prepare("UPDATE orders SET seller_archived=1 WHERE seller_id=? AND status='cancelled'");
        } else {
            $stmt = $conn->prepare("DELETE FROM orders WHERE seller_id=? AND status='cancelled'");
        }
        $stmt->bind_param("i",$seller_id);
        $stmt->execute();
        header("Location:/Ordering/app/seller/seller_dashboard.php"); exit;
    }
}

/* AJAX CONTROLLER */
if (isset($_GET['action'])) {
header('Content-Type: application/json; charset=UTF-8');

/* FETCH ORDERS */
if ($_GET['action'] === 'fetch') {
    $archiveCondition = hasOrderColumn($conn, 'seller_archived') ? ' AND o.seller_archived = 0' : '';
    $sql = "SELECT o.id,o.total,o.status,o.mode,o.seat_numbers,o.payment_method,o.payment_status,o.payment_reference,
            s.full_name student_name,
            GROUP_CONCAT(CONCAT(f.name,' x',oi.quantity) SEPARATOR ', ') items
            FROM orders o
            JOIN students s ON s.id=o.student_id
            LEFT JOIN order_items oi ON oi.order_id=o.id
            LEFT JOIN foods f ON f.id=oi.food_id
            WHERE o.seller_id=?" . $archiveCondition . "
            GROUP BY o.id ORDER BY o.id DESC";
    $stmt=$conn->prepare($sql);
    $stmt->bind_param("i",$seller_id);
    $stmt->execute();
    $res=$stmt->get_result();

    $orders=[];
    while($r=$res->fetch_assoc()){
        $status=strtolower($r['status']);
        if(!in_array($status,['pending','preparing','completed','cancelled'])){
            $status = 'pending';
        }
        $orders[]=[
            'id'=>$r['id'],
            'student_name'=>$r['student_name'],
            'mode'=>$r['mode'],
            'seat_numbers'=>$r['seat_numbers'],
            'items'=>$r['items'] ?? '—',
            'total'=>number_format($r['total'],2),
            'payment_method'=>$r['payment_method'] ?? 'cash',
            'payment_status'=>$r['payment_status'] ?? 'unpaid',
            'payment_reference'=>$r['payment_reference'] ?? '',
            'status'=>$status
        ];
    }
    echo json_encode(['status'=>'success','orders'=>$orders]);
    exit;
}

function releaseReservedSeats($conn, $seller_id, $seat_numbers) {
    $seat_numbers = trim($seat_numbers);
    if (!$seller_id || $seat_numbers === '') return;
    $seats = array_filter(array_map('trim', explode(',', $seat_numbers)));
    if (empty($seats)) return;
    $stmt = $conn->prepare("UPDATE seats SET is_reserved=0 WHERE seller_id=? AND seat_number=?");
    foreach ($seats as $seat) {
        $stmt->bind_param("is", $seller_id, $seat);
        $stmt->execute();
    }
    $stmt->close();
}

/* COMPLETE ORDER */
if ($_GET['action']==='mark_completed') {
    $id = (int)$_POST['order_id'];
    if ($id <= 0) { echo json_encode(['status'=>'error','msg'=>'Invalid order']); exit; }
    $stmt = $conn->prepare("SELECT seat_numbers FROM orders WHERE id=? AND seller_id=? LIMIT 1");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $meta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE orders 
        SET status='completed', student_notified=0
        WHERE id=? AND seller_id=? AND LOWER(status) = 'preparing'
    ");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();
    if ($updated === 1) {
        $recipient=$conn->prepare('SELECT student_id FROM orders WHERE id=? AND seller_id=?');$recipient->bind_param('ii',$id,$seller_id);$recipient->execute();$student=$recipient->get_result()->fetch_assoc();$recipient->close();if($student)sendOrderEmailNotification($conn,(int)$student['student_id'],$id,'order_completed','Ready for Pickup');
    }
    echo json_encode($updated === 1 ? ['status'=>'success'] : ['status'=>'error','msg'=>'Only preparing orders can be completed.']);
    exit;
}

/* CONFIRM CASH PAYMENT AT PICKUP */
if ($_GET['action']==='mark_paid') {
    $id = (int)$_POST['order_id'];
    if ($id <= 0) { echo json_encode(['status'=>'error','msg'=>'Invalid order']); exit; }
    $stmt = $conn->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND seller_id=? AND LOWER(status)='completed' AND payment_method='cash' AND payment_status='unpaid'");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();
    if ($updated !== 1) {
        echo json_encode(['status'=>'error','msg'=>'Only completed cash orders can be marked as paid.']);
        exit;
    }
    echo json_encode(['status'=>'success','msg'=>'Cash payment confirmed.']);
    exit;
}

/* ACCEPT / CANCEL */
if ($_GET['action']==='update_new_order') {
    $id = (int)$_POST['order_id'];
    $type = $_POST['type'] ?? '';
    $meta = null;

    if ($id <= 0 || !in_array($type, ['accept', 'cancel'], true)) {
        echo json_encode(['status'=>'error','msg'=>'Invalid order update']);
        exit;
    }

    if ($type === 'accept') {
        // seller accepted the order
        $status = 'preparing';

        $stmt = $conn->prepare("
            UPDATE orders 
            SET status=?, student_notified=0
            WHERE id=? AND seller_id=? AND LOWER(status)='pending'
        ");
        $stmt->bind_param("sii", $status, $id, $seller_id);
    } 
    else {
        // seller cancelled the order
        $status = 'cancelled';

        $stmt = $conn->prepare("SELECT seat_numbers FROM orders WHERE id=? AND seller_id=? LIMIT 1");
        $stmt->bind_param("ii", $id, $seller_id);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE orders 
            SET status=?, cancelled_by='seller', student_notified=0
            WHERE id=? AND seller_id=? AND LOWER(status) IN ('pending','preparing')
        ");
        $stmt->bind_param("sii", $status, $id, $seller_id);
    }

    $stmt->execute();
    $statusChanged = $stmt->affected_rows === 1;
    $stmt->close();
    if ($statusChanged) {
        $recipient=$conn->prepare('SELECT student_id FROM orders WHERE id=? AND seller_id=?');$recipient->bind_param('ii',$id,$seller_id);$recipient->execute();$student=$recipient->get_result()->fetch_assoc();$recipient->close();if($student){sendOrderEmailNotification($conn,(int)$student['student_id'],$id,$status==='preparing'?'order_preparing':'order_cancelled',$status==='preparing'?'Being Prepared':'Cancelled');if($status==='cancelled'&&!empty($meta['seat_numbers']))sendOrderEmailNotification($conn,(int)$student['student_id'],$id,'reservation_cancelled','Seat reservation cancelled');}
    }
    if ($statusChanged && !empty($meta['seat_numbers'])) {
        releaseReservedSeats($conn, $seller_id, $meta['seat_numbers']);
    }
    echo json_encode($statusChanged ? ['status'=>'success','new_status'=>$status] : ['status'=>'error','msg'=>'This order can no longer be updated.']);
    exit;
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Dashboard | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;color:#0f172a;display:flex;min-height:100vh;}

.sidebar{
  width:230px;background:var(--sidebar-bg);
  color:#fff;height:100vh;position:fixed;
  display:flex;flex-direction:column;z-index:10;}
.sidebar h2{
  margin:0;
  text-align:center;
  padding:1rem 0;
  border-bottom:1px solid rgba(255,255,255,0.06);
  font-family:Montserrat, Poppins, sans-serif}
  
.sidebar a{
  color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;
  display:flex;align-items:center;justify-content:space-between;
  margin:6px 8px;border-radius:8px;transition:0.15s;
}
.sidebar a:hover,.sidebar a.active{
  background:rgba(0, 85, 255, 0.12);color:#fff
}
.sidebar #logoutLink{
  margin-top:auto;
  margin-bottom:14px;
  background:rgba(220,38,38,.16);
  color:#fecaca;
  border:1px solid rgba(252,165,165,.25);
  font-weight:600;
}
.sidebar #logoutLink:hover{background:#dc2626;color:#fff;border-color:#dc2626}

/* 🔴 SIDEBAR BADGE */
.side-badge{
  background:#dc2626;
  color:white;
  font-size:12px;
  padding:2px 7px;
  border-radius:50%;
  display:none;
  margin-left:8px;
}

.main{
  margin-left:230px;
  padding:28px;
  width:calc(100% - 230px);
  box-sizing:border-box;
  display:flex;
  flex-direction:column;
  min-height:100vh;
}

.main > footer {
  margin-top:38%;
}

.topbar{
  background:#fff;padding:15px;border-radius:10px;
  display:flex;justify-content:space-between;
  box-shadow:0 2px 6px #0001
}

.orders-container{
  background:#fff;padding:15px;border-radius:12px;
  margin-top:15px;box-shadow:0 2px 8px #0001
}

.tabs{display:flex;gap:10px;align-items:center}
.tab-btn{background:#e2e8f0;border:none;border-radius:6px;padding:8px 14px;font-weight:600;cursor:pointer}
.tab-btn.active{background:#2563eb;color:#fff}

.clear-all-btn{
  background:#dc2626;color:#fff;border:none;
  padding:7px 12px;border-radius:6px;display:none;font-weight:600;
}

table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:center}
.status{padding:5px 12px;border-radius:20px;font-weight:bold;text-transform:capitalize}
.pending{background:#f59e0b;color:black}
.preparing{background:#3b82f6;color:white}
.completed{background:#16a34a;color:white}
.cancelled{background:#dc2626;color:white}
.accept-btn{background:#10b981;color:white;border:none;padding:6px 12px;border-radius:6px;font-weight:600;}
.cancel-btn{background:#ef4444;color:white;border:none;padding:6px 12px;border-radius:6px;font-weight:600;}
.accept-btn:hover{background: #0ca170ff;}
.cancel-btn:hover{background: #cc2424ff;}
.mark-completed-btn{background:#2563eb;color:white;border:none;padding:6px 12px;border-radius:6px;font-weight:600;}
.no-orders{text-align:center;color:#6b7280;padding:15px}
.tab{display:none}
.tab.active{display:block}
.uc-modal-overlay {position: fixed;inset: 0;background: rgba(0,0,0,0.45);display: none;justify-content: center;align-items: center;z-index: 99999;backdrop-filter: blur(3px);}
.uc-modal-box {background: #ffffff;width: 320px;padding: 25px 25px 20px;border-radius: 14px;text-align: center;box-shadow: 0 8px 30px rgba(0,0,0,0.15);animation: fadeUp 0.25s ease-out;}
.uc-modal-box h3 {margin: 0 0 8px;font-size: 20px;font-weight: 700;color: #1e293b;}
.uc-modal-box p {margin: 0 0 20px;font-size: 15px;color: #475569;}
.uc-modal-buttons {display: flex;justify-content: space-between;gap: 10px;}
.uc-btn {flex: 1;padding: 10px 0;border-radius: 8px;border: none;cursor: pointer;font-size: 14px;font-weight: 600;transition: 0.2s;}
.uc-btn.yes {background: #2563eb;color: white;}
.uc-btn.yes:hover {background: #1d4ed8;}
.uc-btn.no {background: #e2e8f0;color: #1e293b;}
.uc-btn.no:hover {background: #cbd5e1;}

@keyframes fadeUp{
  from {opacity: 0;transform: translateY(15px);}
  to {opacity: 1;transform: translateY(0);}
}


#toast{
  position:fixed;top:20px;left:50%;transform:translateX(-50%);
  background:#0f172a;color:#fff;padding:12px 20px;border-radius:8px;
  opacity:0;transition:.3s
}
#toast.show{opacity:1;top:30px}
.logo-box {width: 100%;padding: 20px 0 5px;text-align: center;border-bottom: 1px solid rgba(255,255,255,0.06);}
.logo-box img {width: 125px;height: auto;border-radius: 12px;object-fit: contain;}

/* ========== FOOTER STYLES ========== */
footer {
  background: #0d131d;
  color: #cbd5e1;
  margin-top: auto;
  margin-left: -28px;
  margin-right: -28px;
  margin-bottom: 0;
  padding: 30px 20px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
  width: calc(100% + 56px);
  box-sizing: border-box;
}

.footer-content {
  max-width: 900px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(3, minmax(220px, 240px));
  gap: 25%;
  margin-bottom: 30px;
  justify-content: center;
}
.footer-section h4 {
  color: #fff;
  font-weight: 700;
  margin: 0 0 15px;
  font-size: 15px;
  font-family: Montserrat, Poppins, sans-serif;
}

.footer-section ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-section li {
  margin-bottom: 10px;
}

.footer-section a {
  color: #cbd5e1;
  text-decoration: none;
  transition: color 0.2s;
}

.footer-section a:hover {
  color: #2563eb;
}

.footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}

@media (max-width: 768px) {
  footer { margin-left:-16px;margin-right:-16px;width:calc(100% + 32px);padding:30px 15px 15px; }
  .footer-content { grid-template-columns: 1fr; gap: 20px; }
  .sidebar{position:static;width:100%;height:auto;min-height:0}
  .main{margin-left:0;width:100%;padding:16px}
  .sidebar #logoutLink{margin-top:6px}
}
</style>
</head>
<body>

<div class="sidebar">
  <!-- Logo Container Added -->
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>
<h2>UniCanteen</h2>

<!-- 🔴 MOVED BADGE HERE -->
<a href="/Ordering/app/seller/seller_dashboard.php" class="active">
   Food Orders
   <span id="sideBadge" class="side-badge">0</span>
</a>

<a href="/Ordering/app/seller/seller_seats.php">Manage Tables</a>
<a href="/Ordering/app/seller/seller_menu.php">Menu & Inventory</a>
<a href="/Ordering/app/seller/seller_analytics.php">Sales Analytics</a>
<a href="/Ordering/app/seller/seller_profile.php">Profile</a>
<a href="#" id="logoutLink">Logout</a>
</div>

<div class="main">

<div class="topbar">
   <h2>Orders</h2>
</div>

<div class="orders-container">

<div class="tabs">
<button class="tab-btn active" data-tab="new_orders">New Orders</button>
<button class="tab-btn" data-tab="pending">Preparing</button>
<button class="tab-btn" data-tab="completed">Completed</button>
<button class="tab-btn" data-tab="cancelled">Cancelled</button>

<form method="POST" style="margin-left:auto">
<button id="clearCompleted" class="clear-all-btn" name="clear_completed">Clear Completed</button>
<button id="clearCancelled" class="clear-all-btn" name="clear_cancelled">Clear Cancelled</button>
</form>
</div>

<?php foreach(['new_orders','pending','completed','cancelled'] as $tab): ?>
<div class="tab <?= $tab==='new_orders'?'active':'' ?>" id="<?= $tab ?>">
<table>
<thead>
<tr>
<th>ID</th><th>Student</th><th>Type</th><th>Seat</th>
<th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th>
</tr>
</thead>
<tbody id="<?= $tab ?>-body"></tbody>
</table>
</div>
<?php endforeach ?>

</div>

  <!-- ========== FOOTER ========== -->
  <footer>
    <div class="footer-content">
      <div class="footer-section">
        <h4>About UniCanteen</h4>
        <ul>
          <li><a href="/Ordering/app/general/about_us.php">About Us</a></li>
          <li><a href="/Ordering/app/general/news.php">News</a></li>
          <li><a href="#careers">Careers</a></li>
        </ul>
      </div>
      
      <div class="footer-section">
        <h4>Support</h4>
        <ul>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="#help">Help Center</a></li>
          <li><a href="#privacy">Privacy Policy</a></li>
          <li><a href="#terms">Terms of Service</a></li>
        </ul>
      </div>
      <div class="footer-section">
        <h4>Contact Info</h4>
        <ul>
          <li>Email: support@unicanteen.edu</li>
          <li>Phone: +63 (2) 1234-5678</li>
          <li>Hours: Mon-Fri, 7AM-6PM</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 UniCanteen System. All rights reserved.</p>
    </div>
  </footer>

</div>

<div id="toast"></div>
<audio id="ding" src="notify.mp3" preload="auto"></audio>
<!-- LOGOUT MODAL (UniCanteen Theme) -->
<div id="logoutModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to logout?</p>

      <div class="uc-modal-buttons">
        <button id="confirmLogout" class="uc-btn yes">Logout</button>
        <button id="cancelLogout" class="uc-btn no">Cancel</button>
      </div>
  </div>
</div>
<script>
const tabs = document.querySelectorAll('.tab-btn');
const bodies = ['new_orders','pending','completed','cancelled'];
const sideBadge = document.getElementById("sideBadge");
const audio = document.getElementById("ding");
const clearCompleted = document.getElementById("clearCompleted");
const clearCancelled = document.getElementById("clearCancelled");
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');
let lastCount = 0;
let toastTimer = null;
const TOAST_DURATION = 6000;
const orderStatuses = new Map();
let ordersRequestInFlight = false;
const sellerOrdersEndpoint = window.location.pathname;

// -------------------
// LOGOUT MODAL
// -------------------
logoutLink.addEventListener('click', e => {
  e.preventDefault();
  logoutModal.style.display = 'flex';
});

confirmBtn.addEventListener('click', () => window.location.href = '/Ordering/app/auth/logout.php');
cancelBtn.addEventListener('click', () => logoutModal.style.display = 'none');
logoutModal.addEventListener('click', e => { if(e.target === logoutModal) logoutModal.style.display = 'none'; });

// -------------------
// TAB SWITCH
// -------------------
tabs.forEach(btn => {
  btn.onclick = () => {
    tabs.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    bodies.forEach(id => {
      document.getElementById(id).classList.toggle('active', id === btn.dataset.tab);
    });
    clearButtons(btn.dataset.tab);
  }
});

function clearButtons(tab){
  clearCompleted.style.display = (tab==='completed')?'inline-block':'none';
  clearCancelled.style.display = (tab==='cancelled')?'inline-block':'none';
}

// -------------------
// TOAST
// -------------------
function toast(msg){
  const t = document.getElementById("toast");
  t.innerText = msg;
  t.classList.add("show");
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    t.classList.remove("show");
    toastTimer = null;
  }, TOAST_DURATION);
}

// -------------------
// ESCAPE HTML
// -------------------
function escapeHtml(str){
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;');
}

// -------------------
// MODAL FUNCTION
// -------------------
function showModal(title, message, buttons){
  const overlay = document.createElement('div');
  overlay.className = 'uc-modal-overlay';
  overlay.style.display = 'flex';

  const box = document.createElement('div');
  box.className = 'uc-modal-box';
  box.innerHTML = `<h3>${escapeHtml(title)}</h3><p>${escapeHtml(message)}</p><div class="uc-modal-buttons"></div>`;
  overlay.appendChild(box);

  buttons.forEach(btn => {
    const b = document.createElement('button');
    b.className = 'uc-btn ' + (btn.class || '');
    b.innerText = btn.text;
    b.onclick = () => { if(btn.onClick) btn.onClick(); overlay.remove(); };
    box.querySelector('.uc-modal-buttons').appendChild(b);
  });

  document.body.appendChild(overlay);
}

// -------------------
// ROW GENERATOR
// -------------------
function row(o){
  let action = '—';
  if(o.status === 'pending'){
    action = `<button class="accept-btn" onclick="updateOrder(${o.id},'accept')">Accept</button>
              <button class="cancel-btn" onclick="updateOrder(${o.id},'cancel')">Decline</button>`;
  }
  if(o.status === 'preparing'){
    action = `<button class="mark-completed-btn" onclick="completeOrder(${o.id})">Complete</button>`;
  }
  if(o.status === 'completed' && o.payment_method === 'cash' && o.payment_status === 'unpaid'){
    action = `<button class="accept-btn" onclick="markPaid(${o.id})">Mark as Paid</button>`;
  }
  const paymentMethod = o.payment_method === 'gcash_sandbox' ? 'GCash' : 'Cash on pickup';
  const paymentStatus = o.payment_status === 'paid' ? 'Paid' : (o.payment_status === 'paid_sandbox' ? 'Payment preview' : 'Unpaid');
  const safeReference = String(o.payment_reference || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const paymentReference = safeReference ? `<br><small>${safeReference}</small>` : '';
  return `<tr>
<td>#${o.id}</td>
<td>${o.student_name}</td>
<td>${o.mode}</td>
<td>${o.seat_numbers||'-'}</td>
<td>${o.items}</td>
<td>₱${o.total}</td>
<td><strong>${paymentMethod}</strong><br><small>${paymentStatus}</small>${paymentReference}</td>
<td><span class="status ${o.status}">${o.status}</span></td>
<td>${action}</td>
</tr>`;
}

// -------------------
// ACCEPT / CANCEL ORDER
// -------------------
function updateOrder(id, type){
  const msg = type==='accept' ? 'Do you want to accept this order?' : 'Do you want to cancel this order?';
  showModal(type==='accept' ? 'Accept Order' : 'Cancel Order', msg, [
    {text:'No', class:'no'},
    {text:'Yes', class:'yes', onClick: () => {
      fetch(sellerOrdersEndpoint + '?action=update_new_order', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`order_id=${id}&type=${type}`
      }).then(r => r.json()).then(data => {
        if (!data || data.status !== 'success') {
          showModal('Order not updated', (data && data.msg) || 'Please refresh and try again.', [{text:'OK', class:'no'}]);
          return;
        }
        loadOrders();
        if(type==='accept'){
          toast("✅ Order accepted");
          document.querySelector('[data-tab="pending"]').click();
        } else {
          toast("❌ Order cancelled");
          document.querySelector('[data-tab="cancelled"]').click();
        }
      }).catch(() => showModal('Order not updated', 'Unable to contact the order service.', [{text:'OK', class:'no'}]));
    }}
  ]);
}

// -------------------
// COMPLETE ORDER
// -------------------
function completeOrder(id){
  showModal('Complete Order', 'Mark this order as completed?', [
    {text:'No', class:'no'},
    {text:'Yes', class:'yes', onClick: () => {
      fetch(sellerOrdersEndpoint + '?action=mark_completed', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`order_id=${id}`
      }).then(r=>r.json()).then(data => {
        if (!data || data.status !== 'success') {
          showModal('Order not updated', (data && data.msg) || 'Please refresh and try again.', [{text:'OK', class:'no'}]);
          return;
        }
        toast("✅ Order completed");
        loadOrders();
        document.querySelector('[data-tab="completed"]').click();
      }).catch(() => showModal('Order not updated', 'Unable to contact the order service.', [{text:'OK', class:'no'}]));
    }}
  ]);
}

function markPaid(id){
  showModal('Confirm Payment', 'Confirm that this cash order has been paid at pickup?', [
    {text:'Cancel', class:'no'},
    {text:'Mark as Paid', class:'yes', onClick: () => {
      fetch(sellerOrdersEndpoint + '?action=mark_paid', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`order_id=${id}`})
        .then(r=>r.json()).then(data=>{ if(data.status==='success'){toast('Payment confirmed');loadOrders();} else showModal('Unable to confirm payment',data.msg||'Please try again.',[{text:'OK',class:'no'}]); });
    }}
  ]);
}

// -------------------
// CLEAR COMPLETED / CANCELLED
// -------------------
clearCompleted.addEventListener('click', e=>{
  e.preventDefault();
  showModal('Clear Completed Orders','Are you sure you want to clear all completed orders?',[
    {text:'Cancel', class:'no'},
    {text:'Clear', class:'yes', onClick:()=> fetch(sellerOrdersEndpoint, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'clear_completed=1'}).then(()=>loadOrders())}
  ]);
});

clearCancelled.addEventListener('click', e=>{
  e.preventDefault();
  showModal('Clear Cancelled Orders','Are you sure you want to clear all cancelled orders?',[
    {text:'Cancel', class:'no'},
    {text:'Clear', class:'yes', onClick:()=> fetch(sellerOrdersEndpoint, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'clear_cancelled=1'}).then(()=>loadOrders())}
  ]);
});

// -------------------
// FETCH / RENDER ORDERS
// -------------------
function loadOrders(){
  if (ordersRequestInFlight || document.hidden) return;
  ordersRequestInFlight = true;
  fetch(sellerOrdersEndpoint + '?action=fetch&_=' + Date.now(), {cache:'no-store'}).then(r=>r.json()).then(data=>{
    if (!data || data.status !== 'success' || !Array.isArray(data.orders)) return;
    const groups = {new_orders:[], pending:[], completed:[], cancelled:[]};

    data.orders.forEach(o=>{
      if(o.status==='pending') groups.new_orders.push(o);
      else if(o.status==='preparing') groups.pending.push(o);
      else if(o.status==='completed') groups.completed.push(o);
      else if(o.status==='cancelled') groups.cancelled.push(o);
    });

    let newOrders = groups.new_orders.length;
    if(newOrders > lastCount){
      audio.play();
      toast("📥 New order received!");
    }

    // Notify seller if any known order switches to cancelled.
    data.orders.forEach(o => {
      const prevStatus = orderStatuses.get(o.id);
      if (prevStatus && prevStatus !== 'cancelled' && o.status === 'cancelled') {
        audio.play();
        toast(`❌ Student cancelled order #${o.id}`);
      }
      orderStatuses.set(o.id, o.status);
    });

    lastCount = newOrders;

    if(newOrders){
      sideBadge.style.display='inline-block';
      sideBadge.innerText=newOrders;
    } else sideBadge.style.display='none';

    Object.keys(groups).forEach(tab=>{
      const body=document.getElementById(tab+'-body');
      body.innerHTML='';
      if(!groups[tab].length){
        body.innerHTML=`<tr><td colspan="9" class="no-orders">No orders</td></tr>`;
        return;
      }
      groups[tab].forEach(o=>body.innerHTML+=row(o));
    });
  }).catch(() => {}).finally(() => { ordersRequestInFlight = false; });
}

loadOrders();
setInterval(loadOrders, 2500);
</script>


</body>
</html>

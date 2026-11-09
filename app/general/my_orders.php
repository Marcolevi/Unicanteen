<?php
require_once __DIR__ . '/../includes/common_footer.php';
session_start();
require_once __DIR__ . '/../../config/db_connection.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    if (isset($_SESSION['seller_id'])) {
        header("Location: /Ordering/app/seller/seller_dashboard.php");
        exit;
    } else {
        header("Location: /Ordering/app/auth/login_register.php");
        exit;
    }
}

$school_id = $_SESSION['student_id']; 
$full_name = $_SESSION['full_name'] ?? 'Student';

// Convert student_id → students.id
$st = $conn->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
$st->bind_param("s", $school_id);
$st->execute();
$res = $st->get_result();
if ($res->num_rows === 0) die("Student not found in database.");
$student_db_id = (int)$res->fetch_assoc()['id'];
$st->close();

function hasOrderColumn($conn, $column) {
    static $cache = [];
    if (!array_key_exists($column, $cache)) {
        $escaped = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$escaped'");
        $cache[$column] = $result && $result->num_rows > 0;
    }
    return $cache[$column];
}

function getOrderArchiveCondition($conn, $role) {
    $column = $role === 'seller' ? 'seller_archived' : 'student_archived';
    return hasOrderColumn($conn, $column) ? " AND o.$column = 0" : '';
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

// Handle cancel and clear requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (isset($_POST['cancel_order_id'])) {
    $cancel_order_id = (int)$_POST['cancel_order_id'];
    $query = $conn->prepare("SELECT seller_id, seat_numbers FROM orders WHERE id = ? AND student_id = ? LIMIT 1");
    $query->bind_param("ii", $cancel_order_id, $student_db_id);
    $query->execute();
    $meta = $query->get_result()->fetch_assoc();
    $query->close();

    $update = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_by='student', student_notified=0 WHERE id = ? AND student_id = ? AND LOWER(status) IN ('pending','preparing')");
    $update->bind_param("ii", $cancel_order_id, $student_db_id);
    $update->execute();
    $cancelled = $update->affected_rows === 1;
    $update->close();

    if (!$cancelled) {
        echo "<script>alert('This order can no longer be cancelled.'); window.location='/Ordering/app/general/my_orders.php';</script>";
        exit;
    }

    if ($meta) {
        releaseReservedSeats($conn, (int)$meta['seller_id'], $meta['seat_numbers']);
    }

    $sellerQuery = $conn->prepare("SELECT seller_id FROM orders WHERE id = ? LIMIT 1");
    $sellerQuery->bind_param("i", $cancel_order_id);
    $sellerQuery->execute();
    $sellerRes = $sellerQuery->get_result();
    if ($sellerRes && $sellerRes->num_rows > 0) {
        $seller_id = (int)$sellerRes->fetch_assoc()['seller_id'];
        $sellerQuery->close();

        $message = "Student cancelled order #{$cancel_order_id}.";
        $notif = $conn->prepare("INSERT INTO notifications (seller_id, student_id, order_id, message, status) VALUES (?, ?, ?, ?, 'cancelled')");
        if ($notif) {
            $notif->bind_param("iiis", $seller_id, $student_db_id, $cancel_order_id, $message);
            $notif->execute();
            $notif->close();
        }
    } else {
        $sellerQuery->close();
    }

    echo "<script>alert('Order cancelled successfully!'); window.location='/Ordering/app/general/my_orders.php';</script>";
    exit;
    }

    if (isset($_POST['clear_completed'])) {
        if (hasOrderColumn($conn, 'student_archived')) {
            $delete = $conn->prepare("UPDATE orders SET student_archived=1 WHERE student_id=? AND status='completed'");
        } else {
            $delete = $conn->prepare("DELETE FROM orders WHERE student_id=? AND status='completed'");
        }
        $delete->bind_param("i", $student_db_id);
        $delete->execute();
        $delete->close();
        echo "<script>alert('All your completed orders have been cleared.'); window.location='/Ordering/app/general/my_orders.php';</script>";
        exit;
    }

    if (isset($_POST['clear_cancelled'])) {
        if (hasOrderColumn($conn, 'student_archived')) {
            $delete = $conn->prepare("UPDATE orders SET student_archived=1 WHERE student_id=? AND status='cancelled'");
        } else {
            $delete = $conn->prepare("DELETE FROM orders WHERE student_id=? AND status='cancelled'");
        }
        $delete->bind_param("i", $student_db_id);
        $delete->execute();
        $delete->close();
        echo "<script>alert('All your cancelled orders have been cleared.'); window.location='/Ordering/app/general/my_orders.php';</script>";
        exit;
    }
}

/*
 * AJAX endpoints:
 *  - ?action=fetch           -> fetch student's orders (existing)
 *  - ?action=count_pending   -> returns {"count":N} for sidebar badge (pending count)
 *  - ?action=check_notifications -> returns list of orders needing student notification (and marks them notified)
 */

// AJAX: fetch orders (existing)
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    header('Content-Type: application/json; charset=utf-8');
    $archiveCondition = getOrderArchiveCondition($conn, 'student');
    $query = "
        SELECT o.*, 
               c.canteen_name,
               GROUP_CONCAT(f.name, ' x', oi.quantity SEPARATOR ', ') AS ordered_foods
        FROM orders o
        LEFT JOIN canteens c ON o.canteen_id = c.id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN foods f ON f.id = oi.food_id
        WHERE o.student_id = ? $archiveCondition
        GROUP BY o.id
        ORDER BY o.id DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $orders = [];
    while ($row = $res->fetch_assoc()) {
        $status = strtolower($row['status'] ?? 'pending');
        if (!in_array($status, ['pending','completed','cancelled','preparing'])) $status='pending';
        $orders[] = [
            'id' => $row['id'],
            'canteen_name' => $row['canteen_name'] ?? 'Unknown',
            'mode' => $row['mode'],
            'seat_numbers' => $row['seat_numbers'] ?: '—',
            'ordered_foods' => $row['ordered_foods'] ?: '—',
            'total' => number_format($row['total'],2),
            'payment_method' => $row['payment_method'] ?? 'cash',
            'payment_status' => $row['payment_status'] ?? 'unpaid',
            'status' => $status
        ];
    }
    echo json_encode(['status'=>'success','orders'=>$orders]);
    exit;
}

// AJAX: count pending orders for badge
if (isset($_GET['action']) && $_GET['action'] === 'count_pending') {
    header('Content-Type: application/json; charset=utf-8');
    $archiveCondition = hasOrderColumn($conn, 'student_archived') ? ' AND student_archived = 0' : '';
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE student_id = ? AND status = 'pending'" . $archiveCondition);
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $count = intval($row['c'] ?? 0);
    echo json_encode(['count' => $count]);
    exit;
}

// AJAX: check for notifications (preparing for notify or cancelled) and mark them as notified
if (isset($_GET['action']) && $_GET['action'] === 'check_notifications') {
    header('Content-Type: application/json; charset=utf-8');

    $archiveCondition = hasOrderColumn($conn, 'student_archived') ? ' AND student_archived = 0' : '';
    $stmt = $conn->prepare("
        SELECT id, status, mode, cancelled_by
        FROM orders 
        WHERE student_id = ? 
          AND student_notified = 0 
          AND status IN ('preparing','completed','cancelled')" . $archiveCondition . "
    ");
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $notify = [];
    $ids = [];

    while ($row = $res->fetch_assoc()) {
        $notify[] = [
            'id' => (int)$row['id'],
            'status' => $row['status'],
            'mode' => $row['mode'],
            'cancelled_by' => $row['cancelled_by']   // ✅ Added!
        ];
        $ids[] = (int)$row['id'];
    }

    // Mark as notified
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql = "UPDATE orders SET student_notified = 1 WHERE id IN ($placeholders)";
        $upd = $conn->prepare($sql);
        $upd->bind_param($types, ...$ids);
        $upd->execute();
        $upd->close();
    }

    echo json_encode(['notifications' => $notify]);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Canteen & Reservation | UniCanteen System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
*{box-sizing:border-box}
body{margin:0;font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;background:#f8fafc;min-height:100vh;color:#0f172a}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:flex;align-items:center;justify-content:space-between;transition:0.15s;border-radius:8px;margin:6px 8px}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.sidebar #logoutLink{margin-top:auto;background:rgba(220,38,38,.16);color:#fecaca;border:1px solid rgba(252,165,165,.25);font-weight:600}
.sidebar #logoutLink:hover{background:#dc2626;color:#fff;border-color:#dc2626}
.content-wrapper{margin-left:230px;display:flex;flex-direction:column;min-height:100vh;}
.main{padding:1.5rem;flex:1;margin-bottom: 33%;}
.topbar { background: white; padding: 1.4rem 2rem; border-radius: 14px; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center; }
.topbar h3 { margin: 0; font-family: Montserrat; }
.username{font-weight:600;color:#0f172a;}
.orders-container{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:1rem;}
h3{margin-top:0;color:#1e293b;}
.tabs-wrapper{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;}
.tabs{display:flex;}
.tabs button{padding:10px 20px;margin-right:10px;border:none;border-radius:6px;background:#e2e8f0;cursor:pointer;font-weight:600;}
.tabs button.active{background:var(--accent);color:#fff;}
.tab-btn:hover{background: #cdd1d5b8;}
.clear-all-btn{padding:10px 12px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;}
.clear-all-btn:hover{background:#b91c1c;}
table{width:100%;border-collapse:collapse;margin-top:1rem;}
th,td{padding:12px;text-align:center;border-bottom:1px solid #e2e8f0;}
th{background-color:#f1f5f9;color:#334155;}
tr:hover{background-color:#f9fafb;}
.status{padding:5px 10px;border-radius:20px;font-size:0.9em;color:white;display:inline-block;min-width:90px;text-align:center;font-weight:600;}
.pending{background:#fbbf24;color:#1e293b;}
.preparing{background:#3b82f6;}
.completed{background:#16a34a;}
.cancelled{background:#dc2626;}
button.cancel-btn{background:#dc2626;color:white;border:none;border-radius:6px;padding:6px 12px;cursor:pointer;font-weight:600;}
button.cancel-btn:hover{background:#b91c1c;}
button.cancel-btn[disabled]{background:#9ca3af;cursor:not-allowed;}
.receipt-btn{display:inline-block;text-decoration:none;background:#2563eb;color:#fff;border-radius:6px;padding:6px 12px;font-weight:600;}
.receipt-btn:hover{background:#1d4ed8;}
button.cancel-btn,.receipt-btn{font-family:inherit;font-size:13px;line-height:normal;padding:6px 12px;}
#completed th:last-child,#completed td:last-child,#cancelled th:last-child,#cancelled td:last-child{display:none;}
.tab-content{display:none;}
.tab-content.active{display:block;}
#toast{position:fixed;top:18px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:12px 18px;border-radius:8px;opacity:0;transition:0.25s;z-index:1000}
#toast.show{opacity:1;top:28px}
.logo-box {width: 100%;padding: 20px 0 5px;text-align: center;border-bottom: 1px solid rgba(255,255,255,0.06);}
.logo-box img {width: 125px;height: auto;border-radius: 12px;object-fit: contain;}
/* UniCanteen Logout Modal */
.uc-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 99999;
  backdrop-filter: blur(3px);
}

.uc-modal-box {
  background: #ffffff;
  width: 320px;
  padding: 25px 25px 20px;
  border-radius: 14px;
  text-align: center;
  box-shadow: 0 8px 30px rgba(0,0,0,0.15);
  animation: fadeUp 0.25s ease-out;
}

.uc-modal-box h3 {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
}

.uc-modal-box p {
  margin: 0 0 20px;
  font-size: 15px;
  color: #475569;
}

.uc-modal-buttons {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.uc-btn {
  flex: 1;
  padding: 10px 0;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: 0.2s;
}

/* Logout Button */
.uc-btn.yes {background: #2563eb;color: white;}
.uc-btn.yes:hover {background: #1d4ed8;}

/* Cancel Button */
.uc-btn.no {
  background: #e2e8f0;
  color: #1e293b;
}
.uc-btn.no:hover {
  background: #cbd5e1;
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(15px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* small responsive tweak */
@media (max-width:700px){
  .sidebar{display:none}
  .content-wrapper{margin-left:0;}
  .main{padding:12px;}
}

/* ========== FOOTER STYLES ========== */
footer {
  background: #0d131d;
  color: #cbd5e1;
  margin-top: 80px;
  padding: 40px 20px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
  width: 100%;
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
  color: var(--accent);
}

.footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}

@media (max-width: 768px) {
  footer { padding: 30px 15px 15px; }
  .footer-content { grid-template-columns: 1fr; gap: 20px; }
}
</style>
</head>
<body>
<!-- audio notification file: place notify.mp3 in same folder -->
<audio id="ding" src="notify.mp3" preload="auto"></audio>
<div id="toast"></div>

<div class="sidebar">
  <!-- Logo Container Added -->
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>

  <h2>UniCanteen</h2>
  <a href="/Ordering/app/student/student_dashboard.php" >Canteen & Reservation</a>
  <a href="#" class="active">My Orders</a>
  <a href="/Ordering/app/general/profile.php">Profile</a>
<a href="#" id="logoutLink">Logout</a>
</div>

<div class="content-wrapper">
  <div class="main">
  <div class="topbar">
       <h3>My Orders</h3>
  </div>

  <div class="orders-container">
    <div class="tabs-wrapper">
      <div class="tabs">
        <button class="tab-btn active" data-tab="pending">Pending</button>
        <button class="tab-btn" data-tab="preparing">Preparing</button>
        <button class="tab-btn" data-tab="completed">Completed</button>
        <button class="tab-btn" data-tab="cancelled">Cancelled</button>
      </div>
      <div class="clear-buttons">
        <form method="POST" style="display:inline;">
          <button type="submit" name="clear_completed" class="clear-all-btn" id="clear-completed-btn">Clear Completed</button>
        </form>
        <form method="POST" style="display:inline;margin-left:5px;">
          <button type="submit" name="clear_cancelled" class="clear-all-btn" id="clear-cancelled-btn">Clear Cancelled</button>
        </form>
      </div>
    </div>

    <div id="pending" class="tab-content active">
      <table>
        <thead><tr><th>Order ID</th><th>Canteen</th><th>Type</th><th>Table No.</th><th>Foods Ordered</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="pending-body"><tr><td colspan="9">Loading...</td></tr></tbody>
      </table>
    </div>

    <div id="preparing" class="tab-content">
      <table>
        <thead><tr><th>Order ID</th><th>Canteen</th><th>Type</th><th>Table No.</th><th>Foods Ordered</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="preparing-body"><tr><td colspan="9">No preparing orders.</td></tr></tbody>
      </table>
    </div>

    <div id="completed" class="tab-content">
      <table>
        <thead><tr><th>Order ID</th><th>Canteen</th><th>Type</th><th>Table No.</th><th>Foods Ordered</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="completed-body"><tr><td colspan="8">No completed orders.</td></tr></tbody>
      </table>
    </div>

    <div id="cancelled" class="tab-content">
      <table>
        <thead><tr><th>Order ID</th><th>Canteen</th><th>Type</th><th>Table No.</th><th>Foods Ordered</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="cancelled-body"><tr><td colspan="8">No cancelled orders.</td></tr></tbody>
      </table>
    </div>
  </div>
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
// ---------------------------
// TABS & CLEAR BUTTONS
// ---------------------------
const tabs = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');
const clearCompletedBtn = document.getElementById('clear-completed-btn');
const clearCancelledBtn = document.getElementById('clear-cancelled-btn');
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');


logoutLink.addEventListener('click', function(e) {
  e.preventDefault();
  logoutModal.style.display = 'flex';
});

confirmBtn.addEventListener('click', function() {
  window.location.href = '/Ordering/app/auth/logout.php';
});

cancelBtn.addEventListener('click', function() {
  logoutModal.style.display = 'none';
});

logoutModal.addEventListener('click', function(e) {
  if (e.target === logoutModal) {
    logoutModal.style.display = 'none';
  }
});


function showModal(title, message, buttons) {
  let overlay = document.createElement('div');
  overlay.className = 'uc-modal-overlay';
  overlay.style.display = 'flex';

  let box = document.createElement('div');
  box.className = 'uc-modal-box';
  box.innerHTML = `
    <h3>${escapeHtml(title)}</h3>
    <p>${escapeHtml(message)}</p>
    <div class="uc-modal-buttons"></div>
  `;
  overlay.appendChild(box);

  buttons.forEach(btn => {
    let b = document.createElement('button');
    b.className = 'uc-btn ' + (btn.class || '');
    b.innerText = btn.text;
    b.onclick = () => {
      if (btn.onClick) btn.onClick();
      overlay.remove();
    };
    box.querySelector('.uc-modal-buttons').appendChild(b);
  });

  document.body.appendChild(overlay);
}

function updateClearButtons(tab){
  clearCompletedBtn.style.display = tab==='completed'?'inline-block':'none';
  clearCancelledBtn.style.display = tab==='cancelled'?'inline-block':'none';
}

function switchTab(tab){
  tabs.forEach(b=>b.classList.remove('active'));
  const btn = Array.from(tabs).find(b=>b.dataset.tab===tab);
  if(btn) btn.classList.add('active');

  tabContents.forEach(tc=>tc.classList.remove('active'));
  const el = document.getElementById(tab);
  if (el) el.classList.add('active');
  updateClearButtons(tab);
}

tabs.forEach(btn=>{
  btn.addEventListener('click', function(){
    switchTab(this.dataset.tab);
  });
});
updateClearButtons('pending');

// ---------------------------
// UTILITY
// ---------------------------
function escapeHtml(s){
  return String(s)
           .replace(/&/g,'&amp;')
           .replace(/"/g,'&quot;')
           .replace(/'/g,'&#39;')
           .replace(/</g,'&lt;')
           .replace(/>/g,'&gt;');
}

function createRow(o){
  const status=o.status.toLowerCase().trim();
  const disabled = (status==='completed'||status==='cancelled')?'disabled':''; 
  const action = status==='pending'?`<button class="cancel-btn" data-id="${o.id}" ${disabled}>Cancel</button>`:'';
  // A receipt is available only after the seller has accepted the order.
  const receipt = status === 'preparing' ? `<a class="receipt-btn" href="/Ordering/app/general/student_receipt.php?order_id=${o.id}" target="_blank" rel="noopener">Receipt PDF</a>` : '';
  const paymentMethod = o.payment_method === 'gcash_sandbox' ? 'GCash' : 'Cash on pickup';
  const paymentStatus = o.payment_status === 'paid' ? 'Paid' : (o.payment_status === 'paid_sandbox' ? 'Payment preview' : 'Unpaid');
  return `<tr data-id="${o.id}">
    <td>#${o.id}</td>
    <td>${escapeHtml(o.canteen_name)}</td>
    <td>${escapeHtml(o.mode)}</td>
    <td>${escapeHtml(o.seat_numbers)}</td>
    <td>${escapeHtml(o.ordered_foods)}</td>
    <td>₱${o.total}</td>
    <td><strong>${paymentMethod}</strong><br><small>${paymentStatus}</small></td>
    <td><span class="status ${status}">${status.charAt(0).toUpperCase()+status.slice(1)}</span></td>
    <td>${action}${receipt}</td>
  </tr>`;
}

// ---------------------------
// CANCEL ORDERS
// ---------------------------
function attachCancelEvents(){
  document.querySelectorAll('button.cancel-btn').forEach(btn=>{
    btn.onclick = () => {
      const orderId = btn.dataset.id;
      showModal('Cancel Order', 'Are you sure you want to cancel this order?', [
        { text: 'No', class: 'no' },
        { text: 'Yes', class: 'yes', onClick: () => {
          btn.disabled = true;
          fetch(myOrdersEndpoint, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`cancel_order_id=${encodeURIComponent(orderId)}`
          }).then(()=>fetchOrdersRealtime(true))
            .catch(()=>toast('Failed to cancel'));
        }}
      ]);
    };
  });
}

clearCompletedBtn.addEventListener('click', e => {
  e.preventDefault();
  showModal('Clear Completed Orders', 'Are you sure you want to clear all completed orders?', [
    { text: 'Cancel', class: 'no' },
    { text: 'Clear', class: 'yes', onClick: () => {
      fetch(myOrdersEndpoint, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'clear_completed=1'
      }).then(()=>fetchOrdersRealtime(true))
        .catch(()=>toast('Failed to clear completed orders'));
    }}
  ]);
});

// ---------------------------
// CLEAR CANCELLED
// ---------------------------
clearCancelledBtn.addEventListener('click', e => {
  e.preventDefault();
  showModal('Clear Cancelled Orders', 'Are you sure you want to clear all cancelled orders?', [
    { text: 'Cancel', class: 'no' },
    { text: 'Clear', class: 'yes', onClick: () => {
      fetch(myOrdersEndpoint, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'clear_cancelled=1'
      }).then(()=>fetchOrdersRealtime(true))
        .catch(()=>toast('Failed to clear cancelled orders'));
    }}
  ]);
});

// ---------------------------
// FETCH ORDERS REALTIME
// ---------------------------
const myOrdersEndpoint = window.location.pathname;

function fetchOrdersRealtime(autoSwitchTab=false){
  fetch(myOrdersEndpoint + '?action=fetch&_=' + Date.now(), {cache:'no-store'}).then(r=>r.json()).then(data=>{
    if(!data||data.status!=='success') return;

    const groups={pending:[],preparing:[],completed:[],cancelled:[]};
    (data.orders||[]).forEach(o=>{
      let st = (o.status || 'pending').toLowerCase().trim();
      if(!['pending','preparing','completed','cancelled'].includes(st)) st='pending';
      groups[st].push(o);
    });

    ['pending','preparing','completed','cancelled'].forEach(tab=>{
      const tbody = document.getElementById(tab+'-body');
      const rows = groups[tab] || [];
      const columnCount = ['completed', 'cancelled'].includes(tab) ? 8 : 9;
      tbody.innerHTML = rows.length ? '' : `<tr><td colspan="${columnCount}">No ${tab} orders.</td></tr>`;
      rows.forEach(r=>tbody.insertAdjacentHTML('beforeend',createRow(r)));
    });
    attachCancelEvents();

    if(typeof autoSwitchTab === 'string'){
      if(groups[autoSwitchTab] && groups[autoSwitchTab].length > 0){
        switchTab(autoSwitchTab);
      }
    } else if(autoSwitchTab){
      ['preparing','completed','cancelled'].some(tab=>{
        if(groups[tab].length>0){
          switchTab(tab);
          return true;
        }
        return false;
      });
    }

    updateOrdersBadge();
  }).catch(console.error);
}

// ---------------------------
// ORDERS BADGE
// ---------------------------
const ordersBadge = document.getElementById('ordersBadge');

function updateOrdersBadge() {
  fetch(myOrdersEndpoint + '?action=count_pending&_=' + Date.now(), {cache:'no-store'})
    .then(r => r.json())
    .then(data => {
      let count = parseInt(data.count || 0, 10);
      ordersBadge.textContent = count;          // always show the number
      if(count > 0){
          ordersBadge.classList.add('nonzero'); // highlight when >0
      } else {
          ordersBadge.classList.remove('nonzero'); // dim when 0
      }
    })
    .catch(err => console.error('Order badge fetch error', err));
}


// ---------------------------
// NOTIFICATIONS (POPUPS + SOUND)
// ---------------------------
const ding = document.getElementById('ding');
const toastEl = document.getElementById('toast');

function toast(msg){
  toastEl.innerText = msg;
  toastEl.classList.add('show');
  setTimeout(()=>toastEl.classList.remove('show'),10000);
}

let notifyLock = false;
function checkNotifications(){
  if (notifyLock) return;
  notifyLock = true;

  fetch(myOrdersEndpoint + '?action=check_notifications&_=' + Date.now(), {cache:'no-store'})
    .then(r => r.json())
    .then(data => {
      notifyLock = false;
      if (!data || !Array.isArray(data.notifications) || data.notifications.length === 0) return;

      let targetTab = null;
      data.notifications.forEach(n=>{
        const status = (n.status || '').toLowerCase().trim();
        let mode = (n.mode || '').toLowerCase().trim().replace(/[\s-]+/g,'');
        let msg = '';
        if(status==='cancelled'){
          let who = n.cancelled_by || '';
          if (who === 'student') {
            msg = "❌ You cancelled this order.";
          } else if (who === 'seller') {
            msg = "❌ Your order was cancelled by the seller.";
          } else {
            msg = "❌ Your order was cancelled.";
          }
          targetTab = 'cancelled';
        } else if(status==='preparing'){
          msg = "📥 Your order is being prepared.";
          if (!targetTab) targetTab = 'preparing';
        } else if(status==='completed'){
          msg = ['takeout','takeaway','pickup'].includes(mode) ? 
                "✅ Your order is ready for pickup." : "✅ Your order is now ready.";
          if (!targetTab) targetTab = 'completed';
        }
        if(msg){
          try{ ding.currentTime=0; ding.play().catch(()=>{}); } catch(e){}
          toast(msg);
        }
      });

      fetchOrdersRealtime(targetTab || true);
    })
    .catch(err => { notifyLock=false; console.error('checkNotifications error', err); });
}

// ---------------------------
// INITIALIZE
// ---------------------------
document.addEventListener('DOMContentLoaded', function(){
  fetchOrdersRealtime(); // fetch immediately
  updateOrdersBadge();   // update badge immediately
  checkNotifications();

  setInterval(fetchOrdersRealtime, 3000);
  setInterval(updateOrdersBadge, 3000);
  setInterval(checkNotifications, 3000);
});

</script>


</body>
</html>


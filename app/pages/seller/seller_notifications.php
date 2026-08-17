<?php
require_once __DIR__ . '/../../components/common_footer.php';
session_start();
include 'db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php"); exit;
}

$seller_id = (int)$_SESSION['seller_id'];

// ------------------------------
// SEND NOTIFICATION
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'], $_POST['student_id'], $_POST['message'])) {
    $student_id = (int)$_POST['student_id'];
    $message = trim($_POST['message']);
    if ($student_id && $message) {
        $status = 'unread';
        $order_id = 0; // default if not related to order
        $stmt = $conn->prepare("INSERT INTO notifications (seller_id, student_id, order_id, message, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $seller_id, $student_id, $order_id, $message, $status);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status'=>'success','msg'=>'Notification sent successfully']);
    } else {
        echo json_encode(['status'=>'error','msg'=>'Student and message are required']);
    }
    exit;
}

// ------------------------------
// FETCH ALL NOTIFICATIONS
// ------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    header('Content-Type: application/json');

    $stmt = $conn->prepare("
        SELECT n.*, s.full_name AS student_name
        FROM notifications n
        LEFT JOIN students s ON n.student_id = s.id
        WHERE n.seller_id = ?
        ORDER BY n.id DESC
    ");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['status'=>'success','data'=>$notifications]);
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Dashboard | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;;color:#0f172a;display:flex;min-height:100vh;}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10;}
.sidebar h2{text-align:center;padding:1.5rem 0;border-bottom:1px solid rgba(255,255,255,0.06);}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;margin:6px 8px;border-radius:8px;transition:0.15s;}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.sidebar a:last-child{margin-top:auto;margin-bottom:14px;background:rgba(220,38,38,.16);color:#fecaca;border:1px solid rgba(252,165,165,.25);font-weight:600}
.sidebar a:last-child:hover{background:#dc2626;color:#fff;border-color:#dc2626}
.main{margin-left:230px;padding:1.5rem;flex:1;display:flex;flex-direction:column;min-height:100vh;}
.main > footer { margin-top:auto; }
.topbar{background:#fff;border-radius:10px;padding:1rem 1.5rem;display:flex;justify-content:space-between;box-shadow:0 2px 6px rgba(0,0,0,0.05)}

.card{background:#fff;padding:1rem;border-radius:10px;margin-top:1rem;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.tabs{display:flex;gap:10px;margin-bottom:10px}
.tab-btn{padding:10px 15px;border:none;border-radius:6px;background:#e2e8f0;font-weight:600;cursor:pointer}
.tab-btn.active{background:var(--accent);color:white}

table{width:100%;border-collapse:collapse;margin-top:8px}
th,td{padding:12px;text-align:center;border-bottom:1px solid #e2e8f0}
thead{background:#f1f5f9}

.status{padding:5px 12px;border-radius:20px;color:#fff;font-weight:600}
.sent{background:#16a34a}
.unread{background:#f59e0b;color:#000}
.cancelled{background:#dc2626}

.clear-group{margin-left:auto}
.clear-group form{display:inline}
.clear-btn{padding:8px 12px;border:none;border-radius:6px;color:#fff;font-weight:600;cursor:pointer}
.clear-sent{background:#16a34a}
.clear-cancelled{background:#dc2626}
.clear-all{background:#334155}

input.message-input {padding:5px;border-radius:5px;border:1px solid #ccc;width:160px;}
button.send-btn {padding:5px 8px;border:none;background:#2563eb;color:white;border-radius:5px;cursor:pointer;margin-left:4px;}

/* ========== FOOTER STYLES ========== */
    footer {
      background: #1e293b;
      color: #cbd5e1;
      margin-top: 24px;
      padding: 30px 20px 20px;
      border-top: 2px solid rgba(37, 99, 235, 0.2);
      font-size: 14px;
      width: calc(100% + 56px);
      margin-left: -28px;
      box-sizing: border-box;
    }

    .footer-content {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 30px;
  margin-bottom: 30px;
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
      footer { padding: 30px 15px 15px; }
      .footer-content { grid-template-columns: 1fr; gap: 20px; }
    }

</style>
</head>
<body>

<div class="sidebar">
<h2>UniCanteen</h2>
<a href="seller_dashboard.php">Food Orders</a>
<a href="seller_seats.php">Manage Seats</a>
<a href="seller_notifications.php" class="active">Notify Students</a>
<a href="seller_menu.php">Menu & Inventory</a>
<a href="seller_analytics.php">Sales Analytics</a>
<a href="seller_profile.php">Profile</a>
<a href="logout.php">Logout</a>
</div>

<div class="main">
<div class="topbar">
<h2>Notifications</h2>

</div>

<div class="card">

<!-- Notification Tabs & Clear -->
<div style="display:flex;align-items:center;margin-bottom:10px">
<div class="tabs">
  <button class="tab-btn active" data-tab="all">All</button>
  <button class="tab-btn" data-tab="sent">Sent</button>
  <button class="tab-btn" data-tab="unread">Unread</button>
  <button class="tab-btn" data-tab="cancelled">Cancelled</button>
</div>

<div class="clear-group">
  <form method="POST"><button name="clear_sent" class="clear-btn clear-sent">Clear Sent</button></form>
  <form method="POST"><button name="clear_cancelled" class="clear-btn clear-cancelled">Clear Cancelled</button></form>
  <form method="POST"><button name="clear_all" class="clear-btn clear-all">Clear All</button></form>
</div>
</div>

<table>
<thead>
<tr>
<th>#</th>
<th>Student</th>
<th>Message</th>
<th>Status</th>
<th>Time</th>
<th>Send Message</th>
</tr>
</thead>
<tbody id="notify-body"><tr><td colspan="6">Loading...</td></tr></tbody>
</table>
</div>
</div>

<!-- ========== FOOTER ========== -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h4>About UniCanteen</h4>
      <ul>
        <li><a href="about_us.php">About Us</a></li>
        <li><a href="news.php">News</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="#careers">Careers</a></li>
        <li><a href="#news">News</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>For Sellers</h4>
      <ul>
        <li><a href="seller_dashboard.php">Dashboard</a></li>
        <li><a href="seller_menu.php">Food Items</a></li>
        <li><a href="seller_seats.php">Table Management</a></li>
        <li><a href="seller_profile.php">Profile</a></li>
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

<script>
const body = document.getElementById('notify-body');
let active = 'all';

tabs.forEach(b=>{
    b.onclick = ()=>{
        tabs.forEach(s=>s.classList.remove('active'));
        b.classList.add('active');
        active = b.dataset.tab;
        fetchNotify();
    }
});

function badge(s){
    return `<span class="status ${s}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`;
}

function fetchNotify(){
    fetch('?action=fetch')
    .then(r=>r.json())
    .then(d=>{
        if(!d || d.status!=='success') return;
        body.innerHTML='';
        let rows = d.data.filter(n => active==='all' || n.status===active);
        if(!rows.length){ 
            body.innerHTML=`<tr><td colspan="6">No notifications</td></tr>`; 
            return; 
        }
        rows.forEach(n=>{
            let tr = document.createElement('tr');
            tr.innerHTML = `<td>#${n.id}</td>
                            <td>${n.student_name ?? 'Unknown'}</td>
                            <td>${n.message}</td>
                            <td>${badge(n.status)}</td>
                            <td>${n.created_at}</td>
                            <td style="display:flex;justify-content:center;align-items:center">
                              <input type="text" placeholder="Enter message" class="message-input" data-student="${n.student_id}">
                              <button class="send-btn" data-student="${n.student_id}">Send</button>
                            </td>`;
            body.appendChild(tr);
        });

        document.querySelectorAll('.send-btn').forEach(btn=>{
            btn.onclick = ()=>{
                const student_id = btn.dataset.student;
                const input = btn.previousElementSibling;
                const msg = input.value.trim();
                if(!msg) { alert('Enter a message'); return; }
                const formData = new FormData();
                formData.append('send_message', '1');
                formData.append('student_id', student_id);
                formData.append('message', msg);
                fetch('seller_notifications.php', {method:'POST', body:formData})
                .then(r=>r.json())
                .then(d=>{
                    alert(d.msg);
                    if(d.status==='success'){
                        input.value='';
                        fetchNotify();
                    }
                });
            };
        });
    });
}

fetchNotify();
</script>

</body>
</html>

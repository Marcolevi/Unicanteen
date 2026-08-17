<?php
require_once __DIR__ . '/../../components/common_footer.php';
session_start();
echo '<style>.sidebar #logoutLink{margin-top:auto!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar #logoutLink:hover{background:#dc2626!important;color:#fff!important;border-color:#dc2626!important}</style>';
include 'db_connection.php';

// -------------------------------
// CHECK IF STUDENT IS LOGGED IN
// -------------------------------
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// -------------------------------
// FETCH STUDENT INFO
// -------------------------------
$stmt = $conn->prepare("SELECT id, full_name, email, contact FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$student = $result->fetch_assoc();
$_SESSION['full_name'] = $student['full_name'];
$student_db_id = $student['id'];
$stmt->close();

// -------------------------------
// FETCH CANTEENS & MENUS
// -------------------------------
$canteens = [];
$menus = [];

$canteen_stmt = $conn->query("SELECT c.id, c.canteen_name, c.description, c.image, c.is_active FROM canteens c JOIN sellers s ON s.id=c.seller_id WHERE s.approval_status='approved' ORDER BY c.canteen_name ASC");

while ($row = $canteen_stmt->fetch_assoc()) {

    // ✅ CANTEEN IMAGE (LONGBLOB)
    if (!empty($row['image'])) {

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $row['image']);
        finfo_close($finfo);

        $canteen_img = 'data:' . $mime . ';base64,' . base64_encode($row['image']);

    } else {
        $canteen_img = 'https://via.placeholder.com/600x400?text=Canteen+Preview';
    }

    $canteens[] = [
        'id' => $row['id'],
        'canteen_name' => $row['canteen_name'],
        'desc' => $row['description'],
        'img' => $canteen_img,
        'is_active' => (int)$row['is_active']
    ];

    // --------------------
    // FETCH FOOD ITEMS
    // --------------------
    $food_stmt = $conn->prepare("SELECT id, name, price, image, stock_quantity, is_available FROM foods WHERE canteen_id=?");
    $food_stmt->bind_param("i", $row['id']);
    $food_stmt->execute();
    $food_result = $food_stmt->get_result();

    $menus[$row['id']] = [];

    while ($food = $food_result->fetch_assoc()) {

        // ✅ FOOD IMAGE (FILE PATH)
        if (!empty($food['image']) && file_exists($food['image'])) {
            $food_img = $food['image'];
        } else {
            $food_img = 'https://via.placeholder.com/300x200?text=Food+Image';
        }

        $menus[$row['id']][] = [
            'id' => $food['id'],
            'name' => $food['name'],
            'price' => $food['price'],
            'img' => $food_img,
            'stock_quantity' => (int)$food['stock_quantity'],
            'is_available' => (int)$food['is_available']
        ];
    }

    $food_stmt->close();
}

$canteen_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Canteen & Reservation | UniCanteen System</title>
<!-- Fonts & styles kept as you had them -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{
  --sidebar-bg:#1e293b;
  --accent:#2563eb;
  --accent-hover:#1d4ed8;
  --muted:#64748b;
  --card-shadow: 0 8px 24px rgba(16,24,40,0.06);
  --card-shadow-soft: 0 2px 8px rgba(2, 6, 23, 0.11);
  --rounded: 12px;
}
*{box-sizing:border-box}
body{margin:0;font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;background:#f8fafc;min-height:100vh;color:#0f172a}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;transition:0.15s;border-radius:8px;margin:6px 8px}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.content-wrap {margin-left:230px;padding:1.5rem;gap:20px;display: grid;grid-template-columns: 1fr;align-items:start; margin-bottom: 100px;}
.content-wrap.two-column {grid-template-columns: 1fr 360px;}
.cart-panel{background:#fff;border-radius:12px;padding:12px;box-shadow:var(--card-shadow);height:calc(100vh - 64px);overflow:auto;position:sticky;top:16px}
.cart-panel.hidden {display:none !important;}
.main-area { width: 100%; min-width: 0; }
.topbar{background:#fff;border-radius:12px;padding:0.8rem 1rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;box-shadow:var(--card-shadow-soft)}
.topbar-left{display:flex;align-items:center;gap:12px}
#searchInput{padding:0.55rem 1rem;border-radius:999px;border:1px solid #aad5ffff;width:320px;outline:none;background:#fbfdff}
.username{font-weight:600;color:#0f172a;font-family:Montserrat, Poppins, sans-serif}
.add-btn {background: var(--accent);color: #fff;padding: 10px 18px;border-radius: 10px;text-decoration: none;font-size: 15px;font-weight: 700;transition: .18s;box-shadow:0 6px 18px rgba(37,99,235,0.12);} 
.add-btn:hover {background: var(--accent-hover);transform:translateY(-1px)}
.food-grid,.canteen-grid{margin-top:20px;display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:18px}
.food-card,.canteen-card{background:#fff;border-radius:12px;box-shadow:var(--card-shadow-soft);padding:0;overflow:hidden;display:flex;flex-direction:column;transition:transform .18s, box-shadow .18s}
.food-card:hover,.canteen-card:hover{transform:translateY(-6px);box-shadow:0 12px 30px rgba(2,6,23,0.08)}
.canteen-card img{width:100%;height:140px;object-fit:cover;border-bottom:1px solid #f1f5f9}
.food-card img{width:100%;height:170px;object-fit:cover;border-bottom:1px solid #f1f5f9}
.food-card-content{padding:12px 14px}
.food-card-content h3{margin:6px 0;font-size:17px;font-weight:700;font-family:Montserrat}
.food-card-content p{margin:4px 0;font-size:14px;color:#475569}
.canteen-status{display:inline-block;padding:4px 8px;border-radius:20px;font-size:12px;font-weight:600;margin-top:4px}.canteen-status.open{background:#dcfce7;color:#166534}.canteen-status.closed{background:#fee2e2;color:#b91c1c}
.card-actions{display:flex;gap:10px;padding:12px 14px;border-top:1px solid #f8fafc}
.btn{flex:1;padding:9px 12px;text-align:center;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;color:#fff;cursor:pointer;border:0}
.small-muted{color:var(--muted);font-size:0.92rem}
.small-muted-2{color:var(--muted);font-size:0.85rem}
.empty-cart{text-align:center;color:var(--muted);padding:26px}
.cart-header{display:flex;flex-direction:column;gap:6px;padding:6px 2px;margin-bottom:6px}
.cart-header .canteen-name{font-weight:700;font-family:Montserrat;font-size:15px}
.cart-header .cart-sub{font-size:13px;color:var(--muted)}
.cart-item{display:flex;gap:12px;align-items:center;padding:10px;border-radius:10px;border:1px solid #f1f5f9;margin-bottom:10px;background:linear-gradient(0deg,#ffffff,#fbfdff)}
.cart-item img{width:62px;height:62px;object-fit:cover;border-radius:8px}
.qty-controls{display:flex;gap:6px;align-items:center}
.qty-controls button{padding:6px 8px;border-radius:8px;border:none;background:#eef6ff;color:var(--accent);font-weight:700;cursor:pointer}
.qty-count{min-width:32px;text-align:center;font-weight:700}
.cart-total{font-family:Montserrat, Poppins, sans-serif;font-weight:900;font-size:20px;margin-top:10px;text-align:right;color:#0f172a}
.cart-total{display: flex;justify-content: space-between; font-weight: bold;padding: 5px 10px;}
.order-type-row { display: flex; gap: 10px; margin-bottom: 8px; }
.order-type-btn {
  flex: 1;
  padding: 12px 0;
  font-weight: bold;
  border: 3px solid transparent;
  border-radius: 14px;
  cursor: pointer;
  color: #fff;
  transition: all 0.25s ease;
}

/* Selected State (very noticeable) */
.order-type-btn.selected {
  transform: scale(1.08);
  box-shadow: 0 0 15px rgba(0,0,0,0.25);
  border-color: #00000050 !important;
  filter: brightness(1.15);
}

.cart-actions-row{margin-top:12px;display:flex;gap:8px}
.cart-actions-row .btn{padding:10px;border-radius:10px}
.payment-method{width:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font:inherit;font-size:13px;}
.sandbox-note{margin:7px 0 0;font-size:11px;color:#64748b;}
.qr-payment-box{background:#fff;width:min(360px,calc(100vw - 32px));padding:24px;border-radius:14px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.18);}
.qr-payment-box img{width:220px;height:220px;display:block;margin:14px auto;border:8px solid #fff;box-shadow:0 1px 8px rgba(15,23,42,.15);}
.empty-illustration{display:flex;flex-direction:column;align-items:center;gap:10px;padding:12px}
#backBtn { background: #1e293be5; color:#fff; border-radius:8px; border:0; padding:8px 12px; cursor:pointer }
#backBtn:hover { background:#566270 }
#contentWrap { display: grid; grid-template-columns: 1fr; }
#contentWrap.two-column { grid-template-columns: 1fr 360px !important; }
#seatSection { display: none; margin-top: 8px; }
#seatGrid { display:grid; grid-template-columns: repeat(5,1fr); gap:10px; margin-top:12px; }
.logo-box {width: 100%;padding: 20px 0 5px;text-align: center;border-bottom: 1px solid rgba(255,255,255,0.06);}
.logo-box img {width: 125px;height: auto;border-radius: 12px;object-fit: contain;}
.seat-box{padding: 12px;text-align:center;border-radius:8px;font-weight:700;background:#ffffff;border:1px solid #e6eef7;transition: transform .12s, box-shadow .12s, background .12s;user-select:none;}
.seat-box:hover { transform: translateY(-4px); box-shadow: 0 8px 18px rgba(2,6,23,0.06); }
.seat-box.available { background: #16a34a; border-color: #15803d; cursor:pointer; color:#ffffff; box-shadow: 0 6px 16px rgba(22,163,74,0.18); }
.seat-box.reserved  { background: #dc2626; border-color: #991b1b; color:#ffffff; cursor:not-allowed; box-shadow: 0 6px 16px rgba(220,38,38,0.18); }
.seat-box.selected { background: #0ea5e9; border-color: #0369a1; box-shadow: 0 0 10px rgba(14,165,233,0.9); color: #fff; transform: scale(1.03); }
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

@media (max-width: 1100px) { #searchInput{width:220px} }
@media (max-width: 980px) { #searchInput{width:200px} .content-wrap.two-column { grid-template-columns: 1fr; } .cart-panel{position:relative;height:auto;top:auto;margin-top:16px} }
@media (min-width:1400px){ .canteen-grid, .food-grid{grid-template-columns:repeat(3, 1fr)} }

/* ========== FOOTER STYLES ========== */
footer {
  position: relative;
  background: #0d131d;
  color: #cbd5e1;
  padding: 40px 20px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
}

.content-wrap { padding-bottom: 10px; min-height: calc(100vh - 200px); }

.footer-wrapper {
  width: calc(100% - 230px);
  margin-left: 230px;
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

.footer-section {
  width: 100%;
  max-width: 240px;
}

.footer-section:nth-child(2) {
  justify-self: center;
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

@media (max-width: 980px) {
  footer { left: 0; width: 100%; }
  .footer-wrapper { width: 100%; margin-left: 0; }
}

@media (max-width: 768px) {
  footer { padding: 30px 15px 15px; }
  .footer-content { grid-template-columns: 1fr; gap: 20px; }
}
</style>
</head>
<body>

<div class="sidebar">
  <!-- Logo Container Added -->
  <div class="logo-box">
    <img src="images/logo2.png" alt="School Logo">
  </div>

  <h2>UniCanteen</h2>
  <a href="#" class="active">Canteen & Reservation</a>
  <a href="my_orders.php">My Orders</a>
  <a href="profile.php">Profile</a>
  <a href="#" id="logoutLink">Logout</a>
</div>

<div class="content-wrap" id="contentWrap">
  <div class="main-area">
    <div class="topbar">
      <div class="topbar-left">
        <button id="backBtn" style="display:none;">← Back</button>
        <input type="text" id="searchInput" placeholder="Search canteens or food..." />
      </div>
      <span class="username">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
    </div>

    <div class="canteen-grid" id="canteenCards">
      <?php foreach($canteens as $canteen): ?>
        <div class="canteen-card" data-id="<?= $canteen['id'] ?>">
          <img src="<?= $canteen['img'] ?>" alt="<?= htmlspecialchars($canteen['canteen_name']) ?>">
          <div class="food-card-content">
            <h3><?= htmlspecialchars($canteen['canteen_name']) ?></h3>
            <p><?= htmlspecialchars($canteen['desc']) ?></p>
            <span class="canteen-status <?= $canteen['is_active'] ? 'open' : 'closed' ?>"><?= $canteen['is_active'] ? 'Open' : 'Closed' ?></span>
          </div>
          <div class="card-actions" style="padding-bottom:14px;">
            <button class="btn" style="background:<?= $canteen['is_active'] ? 'var(--accent)' : '#94a3b8' ?>;" <?= $canteen['is_active'] ? '' : 'disabled' ?> onclick="<?= $canteen['is_active'] ? 'viewMenu('.$canteen['id'].')' : '' ?>"><?= $canteen['is_active'] ? 'View Menu' : 'Currently Closed' ?></button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="seatSection" aria-hidden="true">
      <h3>Select a table</h3>
      <div id="seatGrid"></div>
    </div>

    <div class="food-grid" id="foodCards" style="display:none;"></div>
  </div>

  <div class="cart-panel hidden" id="cartPanel">
    <div class="cart-header">
      <div class="canteen-name" id="cartCanteenName">Select a canteen</div>
      <div class="cart-sub">Order preview — items pinned to selected canteen</div>
    </div>

    <div id="cartContent">
      <div id="cartItems"></div>

      <!-- Keep the original empty-cart block hidden/displayed by JS -->
      <div class="empty-cart" id="cartEmptyBlock">
        <div class="empty-illustration">
          <svg width="120" height="80" viewBox="0 0 120 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
            <rect x="6" y="18" width="108" height="52" rx="8" fill="#F1F7FF" stroke="#E6F0FF"/>
            <circle cx="36" cy="40" r="10" fill="#EAF5FF" stroke="#D6EBFF"/>
            <rect x="58" y="32" width="34" height="16" rx="4" fill="#EAF5FF" stroke="#D6EBFF"/>
          </svg>
          <div style="font-weight:700">Your cart is empty</div>
          <div class="small-muted">Add items from a canteen to see them here</div>
        </div>
      </div>

    </div>

    <div id="cartFooter" style="display:none; position:relative; padding:10px; border-top:1px solid #ccc; background:#f9f9f9;">
      <div class="order-type-row">
        <button class="btn order-type-btn" id="dineInBtn" style="background:#fbbf24; border-color:#fbbf24;">DINE IN</button>
        <button class="btn order-type-btn" id="takeOutBtn" style="background:#dc2626; border-color:#dc2626;">TAKE OUT</button>
      </div>

      <hr style="margin:0 0 8px 0; border:none; border-top:1px solid #ccc;">

      <div class="cart-total">
        <span>Total:</span>
        <span>₱<span id="cartTotal">0.00</span></span>
      </div>

      <div style="margin:10px 0 0;">
        <label for="paymentMethod" style="display:block;font-size:13px;font-weight:700;margin-bottom:5px;">Payment method</label>
        <select id="paymentMethod" class="payment-method">
          <option value="cash">Cash on pickup</option>
          <option value="gcash_sandbox">GCash</option>
        </select>
        <button class="btn" id="gcashDemoBtn" type="button" style="display:none;margin-top:8px;background:#007dfe;">Show GCash QR</button>
        <p class="sandbox-note" id="paymentNote">Pay when you pick up your order.</p>
      </div>

      <div class="cart-actions-row">
        <!-- Replaced go to orders with Place Order -->
        <button class="btn" id="placeOrderBtn" style="flex:1;text-align:center;background:var(--accent);">Place Order</button>
        <button class="btn" id="clearAllBtn" style="flex:0 0 auto;background:#1e293be5">Clear</button>
      </div>
    </div>
  </div>

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

</div>
<!-- UniCanteen Generic Modal -->
<div id="ucModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
      <h3 id="ucModalTitle">Title</h3>
      <p id="ucModalMessage">Message</p>
      <div class="uc-modal-buttons" id="ucModalButtons">
        <!-- Buttons added dynamically -->
      </div>
  </div>
</div>

<div id="gcashQrModal" class="uc-modal-overlay">
  <div class="qr-payment-box">
    <h3 style="margin:0;color:#007dfe;">GCash</h3>
    <p style="margin:7px 0;color:#475569;font-size:13px;">Scan the QR code to preview your payment.</p>
    <img id="gcashQrImage" alt="GCash payment QR code">
    <p id="gcashReference" style="font-size:12px;color:#475569;margin:0 0 15px;"></p>
    <div class="uc-modal-buttons">
      <button id="gcashCancelBtn" class="uc-btn no">Cancel</button>
      <button id="gcashPaidBtn" class="uc-btn yes" style="background:#007dfe;">Confirm Payment</button>
    </div>
  </div>
</div>

<script>
const contentWrap = document.getElementById('contentWrap');
const cartPanel = document.getElementById('cartPanel');
const backBtn = document.getElementById('backBtn');
const canteenContainer = document.getElementById('canteenCards');
const foodContainer = document.getElementById('foodCards');
const cartCanteenName = document.getElementById('cartCanteenName');
const seatSection = document.getElementById('seatSection');
const seatGrid = document.getElementById('seatGrid');

const dineInBtn = document.getElementById('dineInBtn');
const takeOutBtn = document.getElementById('takeOutBtn');
const clearAllBtn = document.getElementById('clearAllBtn');
const placeOrderBtn = document.getElementById('placeOrderBtn');
const paymentMethod = document.getElementById('paymentMethod');
const gcashDemoBtn = document.getElementById('gcashDemoBtn');
const paymentNote = document.getElementById('paymentNote');
const gcashQrModal = document.getElementById('gcashQrModal');
const gcashQrImage = document.getElementById('gcashQrImage');
const gcashReference = document.getElementById('gcashReference');

const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');
const ucModal = document.getElementById('ucModal');
const ucModalTitle = document.getElementById('ucModalTitle');
const ucModalMessage = document.getElementById('ucModalMessage');
const ucModalButtons = document.getElementById('ucModalButtons');

let canteens = <?= json_encode($canteens) ?>;
let menus = <?= json_encode($menus) ?>;
let student_id = <?= $student_db_id ?>;

let currentCanteenId = null;
let orderType = null;
let selectedSeat = null;
let currentView = 'canteens';
let cart = [];

// Keep the available canteen list in sync with administrator approvals without
// interrupting a student who is actively building an order.
setInterval(() => {
    if (cart.length || currentCanteenId) return;
    fetch('student_visible_canteens.php')
        .then(response => response.ok ? response.json() : null)
        .then(ids => {
            if (!ids) return;
            const current = canteens.map(c => `${Number(c.id)}:${Number(c.is_active)}`).sort();
            const incoming = ids.map(c => `${Number(c.id)}:${Number(c.is_active)}`).sort();
            if (current.join(',') !== incoming.join(',')) window.location.reload();
        }).catch(() => {});
}, 15000);

// Refresh menu data only when the student is not in a canteen or building a cart,
// so live stock changes never interrupt an order in progress.
let lastMenuState = '';
function syncVisibleMenuAvailability() {
    if (!currentCanteenId || cart.length || document.hidden) return;
    fetch('student_menu_state.php').then(r => r.ok ? r.json() : null).then(state => {
        if (!state) return;
        let changed = false;
        state.forEach(item => {
            const food = (menus[item.canteen_id] || []).find(food => Number(food.id) === Number(item.id));
            if (food && (Number(food.stock_quantity) !== Number(item.stock) || Number(food.is_available) !== Number(item.available))) {
                food.stock_quantity = item.stock;
                food.is_available = item.available;
                changed = true;
            }
        });
        if (changed) loadMenu(currentCanteenId);
    }).catch(() => {});
}
setInterval(syncVisibleMenuAvailability, 3000);
setInterval(() => {
    if (cart.length || document.hidden) return;
    fetch('student_menu_state.php').then(r => r.ok ? r.json() : null).then(state => {
        if (!state) return;
        const signature = JSON.stringify(state);
        if (lastMenuState && lastMenuState !== signature) {
            state.forEach(item => {
                const food = (menus[item.canteen_id] || []).find(food => Number(food.id) === Number(item.id));
                if (food) { food.stock_quantity = item.stock; food.is_available = item.available; }
            });
            if (currentCanteenId) loadMenu(currentCanteenId);
            else window.location.reload();
        }
        lastMenuState = signature;
    }).catch(() => {});
}, 15000);
let sandboxPaymentReference = '';

// ---------------------------
// INITIAL SETUP
// ---------------------------
document.addEventListener('DOMContentLoaded', () => {
    attachGlobalListeners();
    renderCart([]);
});

// ---------------------------
// GLOBAL LISTENERS
// ---------------------------
function attachGlobalListeners() {
    backBtn.addEventListener('click', backBtnHandler);
    dineInBtn.addEventListener('click', () => toggleOrderType('dine-in'));
    takeOutBtn.addEventListener('click', () => toggleOrderType('take-out'));
    clearAllBtn.addEventListener('click', clearCartHandler);
    placeOrderBtn.addEventListener('click', placeOrderHandler);
    paymentMethod.addEventListener('change', handlePaymentMethodChange);
    gcashDemoBtn.addEventListener('click', showGcashSandboxQr);
    document.getElementById('gcashCancelBtn').addEventListener('click', () => gcashQrModal.style.display='none');
    document.getElementById('gcashPaidBtn').addEventListener('click', () => {
        gcashQrModal.style.display='none';
        paymentNote.textContent = `Payment preview confirmed (${sandboxPaymentReference}). No funds were transferred.`;
    });
    gcashQrModal.addEventListener('click', e => { if (e.target === gcashQrModal) gcashQrModal.style.display='none'; });

    document.getElementById('searchInput').addEventListener('input', searchHandler);

    foodContainer.addEventListener('click', e => {
        const btn = e.target.closest('.order-btn');
        if(!btn || !currentCanteenId) return;
        const foodId = btn.dataset.foodId;
        addToCart(foodId);
    });

    logoutLink.addEventListener('click', e => {
        e.preventDefault();
        logoutModal.style.display = 'flex';
    });

    confirmBtn.addEventListener('click', () => window.location.href='logout.php');
    cancelBtn.addEventListener('click', () => logoutModal.style.display = 'none');
    logoutModal.addEventListener('click', e => {
        if(e.target === logoutModal) logoutModal.style.display = 'none';
    });
}
function showModal(title, message, buttons=[]) {
    ucModalTitle.textContent = title;
    ucModalMessage.textContent = message;

    // Clear previous buttons
    ucModalButtons.innerHTML = '';

    buttons.forEach(btn => {
        const b = document.createElement('button');
        b.textContent = btn.text;
        b.className = 'uc-btn ' + (btn.class || '');
        b.onclick = () => {
            if(btn.onClick) btn.onClick();
            ucModal.style.display = 'none';
        }
        ucModalButtons.appendChild(b);
    });

    ucModal.style.display = 'flex';
}

// Close modal when clicking outside box
ucModal.addEventListener('click', e => {
    if(e.target === ucModal) ucModal.style.display = 'none';
});
// ---------------------------
// ORDER TYPE TOGGLE
// ---------------------------
function toggleOrderType(type) {
    if(!currentCanteenId) return alert('Select a canteen first');
    orderType = type;
    selectedSeat = null;

   if (type === 'dine-in') {
    orderType = 'dine-in';
    currentView = 'seats';            // <--- set view to seats
    seatSection.style.display = 'block';
    foodContainer.style.display = 'none';
    document.getElementById('searchInput').style.display = 'none';
    renderSeats();
} else {
    orderType = 'take-out';
    currentView = 'foods';           // <--- ensure view is foods
    seatSection.style.display = 'none';
    document.getElementById('searchInput').style.display = '';
    viewMenu(currentCanteenId);
}

    // Highlight selected button
    dineInBtn.classList.toggle('selected', type==='dine-in');
    takeOutBtn.classList.toggle('selected', type==='take-out');
}

// ---------------------------
// SEARCH HANDLER
// ---------------------------
function searchHandler() {
    const q = this.value.toLowerCase();
    const footer = document.getElementById('pageFooter');
    const cards = canteenContainer.style.display !== 'none' ? '.canteen-card' : '.food-card';

    document.querySelectorAll(cards).forEach(card => {
        const name = card.querySelector('h3').textContent.toLowerCase();
        card.style.display = name.includes(q) ? '' : 'none';
    });

    if (footer) {
        footer.style.display = q ? 'none' : '';
    }

    if (!currentCanteenId) cartPanel.classList.add('hidden');
}

// ---------------------------
// CART FUNCTIONS
// ---------------------------
function addToCart(foodId) {
    const food = menus[currentCanteenId].find(f => f.id == foodId);
    if (!food) return;

    let item = cart.find(i => i.food_id == foodId);
    if (!food.is_available || food.stock_quantity <= 0) {
        return showModal('Out of Stock', 'This item is currently unavailable.', [{text:'OK', class:'no'}]);
    }
    if (item && item.quantity >= food.stock_quantity) {
        return showModal('Stock Limit', `Only ${food.stock_quantity} item(s) are available.`, [{text:'OK', class:'no'}]);
    }
    if(item){
        item.quantity++;
        item.subtotal = item.price * item.quantity;
    } else {
        cart.push({
            food_id: food.id,
            name: food.name,
            price: parseFloat(food.price),
            quantity: 1,
            subtotal: parseFloat(food.price)
        });
    }
    renderCart(cart);
}

function updateCartQty(foodId, change) {
    let item = cart.find(i => i.food_id == foodId);
    if(!item) return;

    const food = menus[currentCanteenId].find(f => f.id == foodId);
    if (change > 0 && (!food || !food.is_available || item.quantity >= food.stock_quantity)) {
        return showModal('Stock Limit', `Only ${food ? food.stock_quantity : 0} item(s) are available.`, [{text:'OK', class:'no'}]);
    }

    item.quantity += change;
    if(item.quantity <= 0) cart = cart.filter(i => i.food_id != foodId);
    else item.subtotal = item.price * item.quantity;

    renderCart(cart);

    if(cart.length===0){
        orderType = null;
        selectedSeat = null;
        dineInBtn.classList.remove('selected');
        takeOutBtn.classList.remove('selected');
        if(seatSection.style.display==='block'){
            seatSection.style.display='none';
            foodContainer.style.display='grid';
            contentWrap.classList.add('two-column');
            backBtn.style.display='inline-block';
        }
    }
}

function clearCartHandler() {
    if(cart.length === 0) return;

    showModal('Clear Cart', 'Are you sure you want to clear all items?', [
        {text:'Cancel', class:'no'},
        {text:'Clear', class:'yes', onClick: () => {
            cart = [];
            renderCart(cart);
            orderType = null;
            selectedSeat = null;
            dineInBtn.classList.remove('selected');
            takeOutBtn.classList.remove('selected');
        }}
    ]);
}

// ---------------------------
// RENDER CART
// ---------------------------
function renderCart(items){
    const container = document.getElementById('cartItems');
    const emptyBlock = document.getElementById('cartEmptyBlock');
    const footer = document.getElementById('cartFooter');

    container.innerHTML = '';
    let total = 0;

    if(!currentCanteenId){
        cartPanel.classList.add('hidden');
        return;
    }
    cartPanel.classList.remove('hidden');

    if(!items.length){
        emptyBlock.style.display='block';
        footer.style.display='none';
        cartCanteenName.textContent = canteens.find(c => c.id==currentCanteenId)?.canteen_name || '';
        document.getElementById('cartTotal').textContent='0.00';
        return;
    }

    emptyBlock.style.display='none';
    footer.style.display='block';
    cartCanteenName.textContent = canteens.find(c => c.id==currentCanteenId)?.canteen_name || '';

    items.forEach(item=>{
        const div = document.createElement('div');
        div.className='cart-item';
        div.innerHTML=`
            <div style="flex:1">
                <div style="font-weight:700">${escapeHtml(item.name)}</div>
                <div class="small-muted-2">₱${item.price.toFixed(2)}</div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
                <div style="display:flex;align-items:center;gap:6px">
                    <button class="qty-btn minus" onclick="updateCartQty(${item.food_id}, -1)">-</button>
                    <span class="qty-count">${item.quantity}</span>
                    <button class="qty-btn add" onclick="updateCartQty(${item.food_id}, 1)">+</button>
                </div>
                <div style="font-weight:700">₱${item.subtotal.toFixed(2)}</div>
            </div>
        `;
        container.appendChild(div);
        total += item.subtotal;
    });

    document.getElementById('cartTotal').textContent = total.toFixed(2);
}

// ---------------------------
// VIEW MENU
// ---------------------------
function viewMenu(canteenId){
    if(currentCanteenId && currentCanteenId!==canteenId && cart.length>0){
        showModal('Switch Canteen', 'Switching canteen will clear your cart. Continue?', [
            {text:'Cancel', class:'no'},
            {text:'Yes', class:'yes', onClick: () => {
                cart=[];
                loadMenu(canteenId);
            }}
        ]);
        return;
    }
    loadMenu(canteenId);
}

function loadMenu(canteenId){
    currentCanteenId = canteenId;
    currentView='foods';
    const foods = menus[canteenId]||[];
    contentWrap.classList.add('two-column');
    backBtn.style.display='inline-block';
    seatSection.style.display='none';
    canteenContainer.style.display='none';
    foodContainer.style.display='grid';
    foodContainer.innerHTML='';
    foods.forEach(food=>{
        const card = document.createElement('div');
        card.className='food-card';
        const canOrder = Number(food.is_available) === 1 && Number(food.stock_quantity) > 0;
        card.innerHTML=`
            <img src="${food.img}">
            <div class="food-card-content">
                <h3>${escapeHtml(food.name)}</h3>
                <p>₱${food.price.toFixed(2)}</p>
            </div>
            <div class="card-actions">
                <button class="btn order-btn" data-food-id="${food.id}" ${canOrder ? '' : 'disabled'} style="background:${canOrder ? 'var(--accent)' : '#94a3b8'}">${canOrder ? 'Order Now' : 'Out of Stock'}</button>
            </div>
        `;
        foodContainer.appendChild(card);
    });
    cartPanel.classList.remove('hidden');
    renderCart(cart)
}

// ---------------------------
// BACK BUTTON
// ---------------------------
function backBtnHandler() {
    // If viewing foods and cart has items, warn about clearing cart
    if (cart.length > 0 && currentView === 'foods') {
        showModal('Warning', 'Going back will clear your current cart. Continue?', [
            { text: 'Cancel', class: 'no' },
            { text: 'Yes', class: 'yes', onClick: () => goBackToCanteens() }
        ]);
        return;
    }

    // If currently on the seat selection screen, go back to the foods list for this canteen
    if (currentView === 'seats') {
        // Hide seats, show foods
        seatSection.style.display = 'none';
        foodContainer.style.display = 'grid';
        document.getElementById('searchInput').style.display = '';
        currentView = 'foods';
        cartPanel.classList.remove('hidden'); // keep cart visible if it should be
        contentWrap.classList.add('two-column');
        backBtn.style.display = 'inline-block';
        return;
    }

    // Otherwise, go back to canteens (default)
    goBackToCanteens();
}


// Separate function to handle going back to canteen selection
function goBackToCanteens() {
    // hide foods and seats
    foodContainer.style.display = 'none';
    seatSection.style.display = 'none';
    document.getElementById('searchInput').style.display = '';
    canteenContainer.style.display = 'grid';
    contentWrap.classList.remove('two-column');
    backBtn.style.display = 'none';

    currentCanteenId = null;
    currentView = 'canteens';

    // Clear cart visually but keep data only if you want
    renderCart(cart);
}


// ---------------------------
// RENDER SEATS
// ---------------------------
async function renderSeats(){
    seatGrid.innerHTML='';
    try{
        const res = await fetch(`student_fetch_seats.php?canteen_id=${currentCanteenId}`);
        const data = await res.json();
        if(data.status!=='success'){
            seatGrid.innerHTML='<p style="color:red;">Failed loading seats</p>';
            return;
        }

        data.seats.forEach(seat=>{
            const div = document.createElement('div');
            div.className = 'seat-box ' + (seat.available ? 'available' : 'reserved');
            div.textContent = seat.seat_number;

            if(seat.available){
                div.onclick = () => {
                    document.querySelectorAll('#seatGrid .seat-box').forEach(s=>s.classList.remove('selected'));
                    div.classList.add('selected');
                    selectedSeat = seat.seat_number;
                }
            }
            seatGrid.appendChild(div);
        });
    } catch(err){
        console.error(err);
        seatGrid.innerHTML='<p>Error loading seats</p>';
    }
}

// ---------------------------
// PLACE ORDER
// ---------------------------
function handlePaymentMethodChange() {
    sandboxPaymentReference = '';
    const isGcash = paymentMethod.value === 'gcash_sandbox';
    gcashDemoBtn.style.display = isGcash ? 'block' : 'none';
    paymentNote.textContent = isGcash
        ? 'Generate the QR code and confirm the payment preview before placing your order.'
        : 'Pay when you pick up your order.';
}

function showGcashSandboxQr() {
    if (cart.length === 0) {
        return showModal('Cart is empty', 'Add an item before generating a payment QR code.', [{text:'OK', class:'no'}]);
    }
    const total = cart.reduce((sum, item) => sum + item.subtotal, 0).toFixed(2);
    sandboxPaymentReference = `DEMO-${Date.now().toString().slice(-8)}-${Math.floor(Math.random() * 900 + 100)}`;
    const payload = `GCASH-SANDBOX|REF:${sandboxPaymentReference}|AMOUNT:PHP${total}|CANTEEN:${currentCanteenId}`;
    gcashQrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(payload)}`;
    gcashReference.textContent = `Reference: ${sandboxPaymentReference} · ₱${total}`;
    gcashQrModal.style.display = 'flex';
}

async function placeOrderHandler(){
    if(!currentCanteenId) return showModal('Error', 'No canteen selected', [{text:'OK', class:'no'}]);
    if(!orderType) return showModal('Error', 'Select Dine-in or Take-out first', [{text:'OK', class:'no'}]);
    if(orderType==='dine-in' && !selectedSeat) return showModal('Error', 'Select a seat first', [{text:'OK', class:'no'}]);
    if(cart.length===0) return showModal('Error', 'Cart is empty', [{text:'OK', class:'no'}]);
    if (paymentMethod.value === 'gcash_sandbox' && !sandboxPaymentReference) {
        return showModal('GCash', 'Generate the QR code and confirm payment first.', [{text:'OK', class:'no'}]);
    }

    showModal('Place Order', 'Place this order now?', [
        {text:'Cancel', class:'no'},
        {text:'Place', class:'yes', onClick: async () => {
            const params = new URLSearchParams();
            params.append('action','place_order');
            params.append('canteen_id',currentCanteenId);
            params.append('order_type',orderType);
            params.append('cart',JSON.stringify(cart));
            params.append('payment_method', paymentMethod.value);
            params.append('payment_reference', sandboxPaymentReference);
            if(selectedSeat) params.append('seat',selectedSeat);

            try {
                const res = await fetch('update_cart.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:params.toString()
                });
                const raw = await res.text();
                let data;
                try { data = JSON.parse(raw); } catch (_) {
                    throw new Error('The server returned an invalid response.');
                }
                if (res.ok && data.status === 'success') {
                    window.location.assign('my_orders.php');
                    return;
                }
                showModal('Order not placed', data.msg || 'Please try again.', [{text:'OK', class:'no'}]);
            } catch (error) {
                console.error('Checkout failed:', error);
                showModal('Order not placed', error.message || 'Unable to contact the order service.', [{text:'OK', class:'no'}]);
            }
        }}
    ]);
}

// ---------------------------
// UTILS
// ---------------------------
function escapeHtml(text){
    return String(text).replace(/&/g,'&amp;')
                       .replace(/</g,'&lt;')
                       .replace(/>/g,'&gt;')
                       .replace(/"/g,'&quot;')
                       .replace(/'/g,'&#039;');
}
</script>

<!-- ========== FOOTER ========== -->
<div class="footer-wrapper">
  <footer id="pageFooter">
    <div class="footer-content">
    <div class="footer-section">
      <h4>About UniCanteen</h4>
      <ul>
        <li><a href="about_us.php">About Us</a></li>
        <li><a href="news.php">News</a></li>
        <li><a href="#contact">Careers</a></li>
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

</body>
</html>

<?php
require_once __DIR__ . '/../includes/common_footer.php';
require_once __DIR__ . '/../includes/food_images.php';
session_start(); echo '<style>.sidebar #logoutLink{margin-top:auto!important;margin-bottom:14px!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar #logoutLink:hover{background:#dc2626!important;color:#fff!important}</style>';
require_once __DIR__ . '/../../config/db_connection.php';

// ---------------------------
// Check if seller is logged in
// ---------------------------
if (!isset($_SESSION['seller_id'])) {
    header("Location: /Ordering/app/auth/login_register.php");
    exit;
}

$seller_id = $_SESSION['seller_id'];

// ---------------------------
// Check for messages from actions (delete/add/edit)
// ---------------------------
$msg = $_GET['msg'] ?? '';

// ---------------------------
// Fetch seller's canteen
// ---------------------------
$canteen_q = $conn->query("SELECT id, canteen_name FROM canteens WHERE seller_id = $seller_id LIMIT 1");
$canteen = $canteen_q->fetch_assoc();

if (!$canteen) {
    die("No canteen found for this seller.");
}

$canteen_id = $canteen['id'];

// ---------------------------
// Fetch foods belonging to this canteen
// ---------------------------
$foods = $conn->query("SELECT * FROM foods WHERE canteen_id = $canteen_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Dashboard | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{
  --sidebar-bg:#1e293b;
  --accent:#2563eb;
  --accent-hover:#1d4ed8;
  --card-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;;color:#0f172a;display:flex;min-height:100vh;}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10;}
.sidebar h2{
  margin:0;
  text-align:center;
  padding:1rem 0;
  border-bottom:1px solid rgba(255,255,255,0.06);
  font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;margin:6px 8px;border-radius:8px;transition:0.15s;}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.main{margin-left:230px;padding:1.5rem;flex:1;display:flex;flex-direction:column;min-height:100vh;}
.main > footer { margin-top: 10%; }
.topbar{background:#fff;border-radius:12px;padding:0.8rem 1rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 6px rgba(0,0,0,0.06);}
.username{font-weight:600;color:#1e293b;}
.add-btn {background: var(--accent);color: #fff;padding: 10px 18px;border-radius: 8px;text-decoration: none;font-size: 15px;font-weight: 600;transition: .2s;}
.add-btn:hover {background: var(--accent-hover);}
.food-grid{margin-top:20px;display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:20px;}
.food-card{background:#fff;border-radius:14px;box-shadow:var(--card-shadow);padding:0;overflow:hidden;display:flex;flex-direction:column;transition:0.2s;}
.food-card img{width:100%;height:210px;object-fit:cover;}
.food-card-content{padding:15px 18px;}
.food-card-content h3{margin:5px 0;font-size:20px;font-weight:600;}
.food-card-content p{margin:5px 0;font-size:17px;color:#475569;}
.stock{font-size:14px!important;font-weight:600;color:#166534!important;}
.stock.out{color:#b91c1c!important;}
.card-actions{display:flex;gap:10px;padding:15px 18px;border-top:1px solid #f1f5f9;}
.btn{flex:1;padding:10px;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;color:#fff;}
.edit-btn{ background:var(--accent);}
.edit-btn:hover{ background:var(--accent-hover);}
.delete-btn{ background:#dc2626;}
.delete-btn:hover{ background:#b91c1c}
.logo-box {width: 100%;padding: 20px 0 5px;text-align: center;border-bottom: 1px solid rgba(255,255,255,0.06);}
.logo-box img {width: 125px;height: auto;border-radius: 12px;object-fit: contain;}
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

/* ========== FOOTER STYLES ========== */
footer {
  background: #0d131d;
  color: #cbd5e1;
  margin-top: auto;
  margin-left: -1.5rem;
  margin-right: 0;
  margin-bottom: 0;
  padding: 30px 20px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
  width: calc(100% + 1.5rem);
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
  footer { padding: 30px 15px 15px; }
  .footer-content { grid-template-columns: 1fr; gap: 20px; }
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
  <!-- Logo Container Added -->
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>
<h2>UniCanteen</h2>
  <a href="/Ordering/app/seller/seller_dashboard.php">Food Orders</a>
  <a href="/Ordering/app/seller/seller_seats.php">Manage Tables</a>
  <a href="/Ordering/app/seller/seller_menu.php"class="active">Menu & Inventory</a>
  <a href="/Ordering/app/seller/seller_analytics.php">Sales Analytics</a>
  <a href="/Ordering/app/seller/seller_profile.php">Profile</a>
  <a href="#" id="logoutLink">Logout</a>
</div>


<!-- MAIN PAGE -->
<div class="main">

  <div class="topbar">
    <h2>Menu & Inventory</h2>

    <a class="add-btn" href="/Ordering/app/seller/seller_add_food.php">+ Add New Food</a>
  </div>


  <!-- Food Cards -->
  <div class="food-grid">

    <?php if ($foods->num_rows == 0): ?>
      <p style="font-size:18px; color:#475569;">No food items yet. Click "Add New Food" to get started.</p>
    <?php else: ?>

      <?php while($food = $foods->fetch_assoc()): ?>
        <div class="food-card">
          
          <img src="<?= htmlspecialchars(foodImageDisplayUrl($food['image'])) ?>" alt="Food Image">

          <div class="food-card-content">
            <h3><?= htmlspecialchars($food['name']) ?></h3>
            <p class="stock <?= (!$food['is_available'] || $food['stock_quantity'] <= 0) ? 'out' : '' ?>"><?= !$food['is_available'] ? 'Unavailable' : ($food['stock_quantity'] > 0 ? (int)$food['stock_quantity'] . ' in stock' : 'Out of stock') ?></p>
            <p>₱<?= number_format($food['price'], 2) ?></p>
          </div>

          <div class="card-actions">
            <a class="btn edit-btn" href="/Ordering/app/seller/seller_edit_food.php?id=<?= $food['id'] ?>">Edit</a>
            <a class="btn delete-btn" href="/Ordering/app/seller/actions/seller_delete_food.php?id=<?= $food['id'] ?>"
            data-id="<?= $food['id'] ?>" onclick="showDeleteModal(event)">
            Delete
          </a>
          </div>
        </div>
      <?php endwhile; ?>

    <?php endif; ?>

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
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
    <h3>Delete Food Item</h3>
    <p>Are you sure you want to delete this food item?</p>
    <div class="uc-modal-buttons">
      <button id="confirmDelete" class="uc-btn yes">Delete</button>
      <button id="cancelDelete" class="uc-btn no">Cancel</button>
    </div>
  </div>
</div>

<script>
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');
// Delete Food Modal
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDelete');
const cancelDeleteBtn = document.getElementById('cancelDelete');

let deleteUrl = ""; // URL to redirect when confirmed

function showDeleteModal(e) {
    e.preventDefault();
    deleteUrl = e.currentTarget.href;
    deleteModal.style.display = 'flex';
}

confirmDeleteBtn.addEventListener('click', () => {
    window.location.href = deleteUrl;
});

cancelDeleteBtn.addEventListener('click', () => {
    deleteModal.style.display = 'none';
});

deleteModal.addEventListener('click', (e) => {
    if (e.target === deleteModal) deleteModal.style.display = 'none';
});

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
const foodGrid = document.querySelector('.food-grid');

function loadFoods(){
    fetch('/Ordering/app/seller/actions/seller_fetch_foods.php')
    .then(res=>res.json())
    .then(data=>{
        foodGrid.innerHTML = '';
        if(data.length===0){
            foodGrid.innerHTML = '<p style="font-size:18px; color:#475569;">No food items yet. Click "Add New Food" to get started.</p>';
        } else {
            data.forEach(food=>{
                const card = document.createElement('div');
                card.className='food-card';
                card.innerHTML=`
                    <img src="${food.image}" alt="Food Image">
                    <div class="food-card-content">
                        <h3>${food.name}</h3>
                        <p class="stock ${!Number(food.is_available) || Number(food.stock_quantity) <= 0 ? 'out' : ''}">${!Number(food.is_available) ? 'Unavailable' : (Number(food.stock_quantity) > 0 ? `${parseInt(food.stock_quantity, 10)} in stock` : 'Out of stock')}</p>
                        <p>₱${parseFloat(food.price).toFixed(2)}</p>
                    </div>
                    <div class="card-actions">
                        <a class="btn edit-btn" href="/Ordering/app/seller/seller_edit_food.php?id=${food.id}">Edit</a>
                        <a class="btn delete-btn" href="/Ordering/app/seller/actions/seller_delete_food.php?id=${food.id}" data-id="${food.id}" onclick="showDeleteModal(event)">Delete</a>
                    </div>`;
                foodGrid.appendChild(card);
            });
        }
    });
}

// Load foods every time page loads
loadFoods();
// Keep inventory and availability in sync with admin moderation while this page is open.
setInterval(() => { if (!document.hidden) loadFoods(); }, 10000);

</script>

</body>
</html>

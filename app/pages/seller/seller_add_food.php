<?php
require_once __DIR__ . '/../../components/common_footer.php';
session_start(); echo '<style>.sidebar #logoutLink{margin-top:auto!important;margin-bottom:14px!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar #logoutLink:hover{background:#dc2626!important;color:#fff!important}</style>';
include 'db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit;
}

$seller_id = $_SESSION['seller_id'];
$errors = [];

// Fetch seller's canteen
$canteen_q = $conn->query("SELECT id, canteen_name FROM canteens WHERE seller_id = $seller_id LIMIT 1");
$canteen = $canteen_q->fetch_assoc();
if (!$canteen) die("You must create a canteen first.");
$canteen_id = $canteen['id'];

// Handle AJAX submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock_quantity = max(0, intval($_POST['stock_quantity'] ?? 0));
    $image = null;

    if ($name === "") $errors[] = "Food name is required.";
    if ($price <= 0) $errors[] = "Invalid price.";

    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image format.";
        } else {
            $target_dir = "uploads/foods/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = uniqid("food_") . "." . $ext;
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $target_file;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    if ($errors) {
        echo json_encode(['success'=>false,'message'=>implode(", ",$errors)]);
    } else {
        $stmt = $conn->prepare("INSERT INTO foods (canteen_id, name, price, image, stock_quantity) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsi", $canteen_id, $name, $price, $image, $stock_quantity);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success'=>true,
            'name'=>$name,
            'price'=>$price,
            'image'=>$image
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Food | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;display:flex;}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:.9rem 1.4rem;display:block;border-radius:8px;margin:4px 8px;}
.sidebar a:hover,.sidebar a.active{background:rgba(0,85,255,.12);color:#fff;}
.main{margin-left:230px;padding:1.5rem;width:100%;display:flex;flex-direction:column;min-height:100vh;}
.main > footer { margin-top:auto; }
.topbar{background:white;padding:16px 28px;border-radius:14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;}
.topbar button {background: var(--accent);color: #fff;border: none;padding: 8px 16px;border-radius: 8px;font-weight: 600;cursor: pointer;}
.topbar button:hover {background: #1d4ed8;}
.container{display:grid;grid-template-columns:1.2fr 2fr;gap:20px;background:white;padding:28px;border-radius:14px;width:95.5%;max-width:none;margin:0;}
.preview-card{background:#f8fafc;border-radius:12px;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:15px;}
.preview-card img{width:100%;height:260px;object-fit:cover;border-radius:10px;border:1px solid #ddd;display:none;}
.no-image{height:260px;display:flex;align-items:center;justify-content:center;color:#888;border:1px dashed #ccc;border-radius:10px;width:100%;}
.form label{font-weight:600;margin-bottom:6px;display:block;}
.form input{padding:13px;margin-bottom:16px;width:96.4%;border-radius:8px;border:1px solid #ccc;}
.file-box{background:#eef2ff;border-radius:8px;padding:12px;text-align:center;cursor:pointer;margin-bottom:16px;}
.file-box:hover{background:#e0e7ff}
.file-box input{display:none}
.btn{flex:1;padding:13px;border:none;border-radius:8px;font-weight:600;cursor:pointer;}
.btn{background: #2563eb;color:white}
.btn:hover{background:#1d4ed8}
.btn-delete{background:#fee2e2;color:#b91c1c;}
.btn-delete:hover{background:#fecaca}
.button-row {display:flex;justify-content:flex-start;gap:10px;}

.errors{color:#b91c1c;margin-bottom:12px}
.success{color:#16a34a;margin-bottom:12px}
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
@media(max-width:800px){
    .container{grid-template-columns:1fr}
}



</style>
</head>
<body>

<div class="sidebar">
  <div class="logo-box"><img src="images/logo2.png" alt="School Logo"></div>
  <h2>UniCanteen</h2>
  <a href="seller_dashboard.php">Food Orders</a>
  <a href="seller_seats.php">Manage Seats</a>
  <a href="seller_menu.php" class="active">Menu & Inventory</a>
  <a href="seller_analytics.php">Sales Analytics</a>
  <a href="seller_profile.php">Profile</a>
  <a href="#" id="logoutLink">Logout</a>
</div>

<div class="main">
<div class="topbar">
    <button onclick="window.history.back()">← Back</button>
    <h3 style="margin:0;">Add Food</h3>
</div>

<div class="container">

<div class="preview-card">
    <img id="previewImg">
    <div class="no-image" id="noImage">Image Preview</div>
</div>

<div class="form">
<form method="POST" enctype="multipart/form-data" id="foodForm">
<label>Food Name</label>
<input name="name" required>
<label>Price (₱)</label>
<input type="number" step="0.01" name="price" required>
<label>Starting stock</label>
<input type="number" min="0" step="1" name="stock_quantity" value="0" required>
<label>Upload image</label>
<label class="file-box">
    Click to choose image
    <input type="file" name="image" accept="image/*" onchange="previewImage(this)">
</label>
<div class="button-row">
    <button type="submit" class="btn">Add Food</button>
    <button type="button" class="btn btn-delete" onclick="deleteImage()">Remove Image</button>
</div>
</form>
</div>
</div>


</div>

<!-- Success Modal -->
<div id="saveModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
    <h3>Success!</h3>
    <p>Food added successfully.</p>
    <div class="uc-modal-buttons">
      <button class="uc-btn yes" onclick="closeModal()">OK</button>
    </div>
  </div>
</div>

<!-- Logout Modal -->
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
// logout modal logic
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');
logoutLink.addEventListener('click', e=>{ e.preventDefault(); logoutModal.style.display='flex'; });
confirmBtn.addEventListener('click', ()=> window.location.href='logout.php');
cancelBtn.addEventListener('click', ()=> logoutModal.style.display='none');
logoutModal.addEventListener('click', e=> { if(e.target===logoutModal) logoutModal.style.display='none'; });

// image preview
function previewImage(input){
    const img = document.getElementById("previewImg");
    const text = document.getElementById("noImage");
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e=> { img.src = e.target.result; img.style.display='block'; text.style.display='none'; };
        reader.readAsDataURL(input.files[0]);
    } else { img.style.display='none'; text.style.display='flex'; }
}
function deleteImage(){
    const img=document.getElementById("previewImg");
    const text=document.getElementById("noImage");
    const fileInput=document.querySelector('input[name="image"]');
    img.src=""; img.style.display='none'; text.style.display='flex'; fileInput.value="";
}

// AJAX form submission
const form = document.getElementById('foodForm');
form.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(form);
    formData.append('ajax_save',1);

    fetch('<?= $_SERVER['PHP_SELF'] ?>', { method:'POST', body:formData })
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            document.getElementById('saveModal').style.display='flex';
            // reset form
            form.reset();
            deleteImage();

            // Optional: real-time update in menu page if open
            if(window.opener && typeof window.opener.loadFoods==='function'){
                window.opener.loadFoods();
            }

        } else { alert(data.message); }
    }).catch(err=>console.error(err));
});

function closeModal(){ document.getElementById('saveModal').style.display='none'; }
</script>

</body>
</html>

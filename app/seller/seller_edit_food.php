<?php
require_once __DIR__ . '/../includes/common_footer.php';
require_once __DIR__ . '/../includes/food_images.php';
session_start(); echo '<style>.sidebar #logoutLink{margin-top:auto!important;margin-bottom:14px!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar #logoutLink:hover{background:#dc2626!important;color:#fff!important}</style>';
require_once __DIR__ . '/../../config/db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: /Ordering/app/auth/login_register.php");
    exit;
}

$seller_id = $_SESSION['seller_id'];
$errors = [];
$success = "";

// Get food ID
$food_id = intval($_GET['id'] ?? 0);
if (!$food_id) die("Invalid food ID.");

// Fetch food
$stmt = $conn->prepare("
    SELECT f.*, c.seller_id 
    FROM foods f 
    JOIN canteens c ON f.canteen_id = c.id 
    WHERE f.id = ? LIMIT 1
");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Authorization
if (!$food || $food['seller_id'] != $seller_id) die("Unauthorized access");

// DELETE IMAGE REQUEST (no modal)
if (isset($_POST['delete_image'])) {
    if ($oldImagePath = foodImageFilesystemPath($food['image'])) {
        if (is_file($oldImagePath)) unlink($oldImagePath);
    }
    $stmt = $conn->prepare("UPDATE foods SET image=NULL WHERE id=?");
    $stmt->bind_param("i", $food_id);
    $stmt->execute();
    $stmt->close();
    $food['image'] = null;
}

// HANDLE AJAX SAVE
if (isset($_POST['ajax_save'])) {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock_quantity = max(0, intval($_POST['stock_quantity'] ?? 0));
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $image = $food['image'];
    $response = ['success'=>false, 'message'=>'', 'name'=>$name, 'price'=>$price, 'image'=>$image];

    if ($name === "") $response['message'] = "Food name cannot be empty.";
    elseif ($price <= 0) $response['message'] = "Invalid price.";
    else {
        // Upload image if exists
        if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) $response['message']="Invalid file format.";
            else {
                $newName=uniqid("food_").".".$ext;
                $image='uploads/foods/'.$newName;
                $path=foodImageFilesystemPath($image);
                if(!is_dir(dirname($path))) mkdir(dirname($path),0777,true);
                if(move_uploaded_file($_FILES['image']['tmp_name'],$path)){
                    if($oldImagePath = foodImageFilesystemPath($food['image'])) if(is_file($oldImagePath)) unlink($oldImagePath);
                } else $response['message']="Upload failed.";
            }
        }
        if (!$response['message']){
            $stmt = $conn->prepare("UPDATE foods SET name=?, price=?, image=?, stock_quantity=?, is_available=? WHERE id=?");
            $stmt->bind_param("sdsiii", $name, $price, $image, $stock_quantity, $is_available, $food_id);
            $stmt->execute();
            $stmt->close();
            $response['success']=true;
            $response['message']="Food updated successfully.";
            $response['image']=foodImageDisplayUrl($image);
        }
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Food | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;color:#0f172a;display:flex;min-height:100vh;}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10;}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;margin:6px 8px;border-radius:8px;transition:0.15s;}
.sidebar a:hover,.sidebar a.active{background:rgba(0,85,255,.12);color:#fff}
.main{margin-left:230px;padding:1.5rem;flex:1;display:flex;flex-direction:column;min-height:100vh;}
.main > footer { margin-top:auto; }
.topbar{background:white;padding:16px 28px;border-radius:14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;}
.topbar button {background: var(--accent);color: #fff;border: none;padding: 8px 16px;border-radius: 8px;font-weight: 600;cursor: pointer;}
.topbar button:hover {background: #1d4ed8;}
.container{display:grid;grid-template-columns:1.2fr 2fr;gap:20px;background:white;padding:28px;border-radius:14px;width:95.5%;max-width:none;margin:0;}
.preview-card{background:#f8fafc;border-radius:12px;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:15px;}
.preview-card img{width:100%;height:260px;object-fit:cover;border-radius:10px;border:1px solid #ddd;display:block;}
.no-image{height:260px;display:flex;align-items:center;justify-content:center;color:#888;border:1px dashed #ccc;border-radius:10px;width:100%;}
.form label{font-weight:600;font-size:14px;margin-bottom:6px;display:block;}
.form input{padding:12px;width: 96.4%;margin-bottom:16px;border-radius:8px;border:1px solid #ccc;}
.file-box{background:#eef2ff;border-radius:8px;padding:12px;text-align:center;cursor:pointer;margin-bottom:16px;}
.file-box:hover{background:#e0e7ff}
.file-box input{display:none}
.actions{display:flex;gap:12px;}
.btn{flex:1;padding:13px;border:none;border-radius:8px;font-weight:600;cursor:pointer;}
.btn-save{background:#2563eb;color:white}
.btn-save:hover{background:#1d4ed8}
.btn-delete{background:#fee2e2;color:#b91c1c;}
.btn-delete:hover{background:#fecaca}
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
@keyframes fadeUp{from {opacity: 0;transform: translateY(15px);}to {opacity: 1;transform: translateY(0);}}
@media (max-width:800px){.container{grid-template-columns:1fr}}

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
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>
<h2>UniCanteen</h2>
<a href="/Ordering/app/seller/seller_dashboard.php">Food Orders</a>
<a href="/Ordering/app/seller/seller_seats.php">Manage Seats</a>
<a href="/Ordering/app/seller/seller_menu.php" class="active">Menu & Inventory</a>
<a href="/Ordering/app/seller/seller_analytics.php">Sales Analytics</a>
<a href="/Ordering/app/seller/seller_profile.php">Profile</a>
<a href="#" id="logoutLink">Logout</a>
</div>

<div class="main">
<div class="topbar">
    <button onclick="window.history.back()">← Back</button>
    <h3 style="margin:0;">Edit Food</h3>
</div>

<div class="container">
<div class="preview-card">
    <img id="previewImg" src="<?= !empty($food['image']) ? $food['image'] : '' ?>" <?= empty($food['image']) ? 'style="display:none;"' : '' ?>>
    <div class="no-image" id="noImage" <?= !empty($food['image']) ? 'style="display:none;"' : '' ?>>
         <?= empty($food['image']) ? 'No image uploaded' : '' ?>
    </div>
</div>

<div class="form">
<form method="POST" enctype="multipart/form-data" id="foodForm">
<label>Food name</label>
<input name="name" value="<?= htmlspecialchars($food['name']) ?>" required>

<label>Price (₱)</label>
<input type="number" step="0.01" name="price" value="<?= htmlspecialchars($food['price']) ?>" required>
<label>Stock quantity</label>
<input type="number" min="0" step="1" name="stock_quantity" value="<?= (int)$food['stock_quantity'] ?>" required>
<label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;"><input style="width:auto;margin:0;" type="checkbox" name="is_available" value="1" <?= $food['is_available'] ? 'checked' : '' ?>> Available for ordering</label>

<label>Upload new image</label>
<label class="file-box">Click to choose file
<input type="file" name="image" accept="image/*" onchange="previewImage(this)"></label>

<div class="actions">
    <button class="btn btn-save" type="submit">Save</button>
    <?php if(!empty($food['image'])): ?>
        <button class="btn btn-delete" type="button" onclick="deleteImage()">Remove Image</button>
    <?php endif; ?>
</div>
</form>
</div>
</div>
</div>

<!-- MODALS -->
<div id="saveModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
    <h3>Success</h3>
    <p>Food updated successfully.</p>
    <div class="uc-modal-buttons">
        <button class="uc-btn yes" onclick="closeSaveModal()">OK</button>
    </div>
  </div>
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
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');

logoutLink.addEventListener('click', e=>{
  e.preventDefault();
  logoutModal.style.display='flex';
});
confirmBtn.addEventListener('click', ()=>window.location.href='/Ordering/app/auth/logout.php');
cancelBtn.addEventListener('click', ()=>logoutModal.style.display='none');
logoutModal.addEventListener('click', e=>{if(e.target===logoutModal) logoutModal.style.display='none';});

function previewImage(input){
    const img=document.getElementById("previewImg");
    const text=document.getElementById("noImage");
    if(input.files && input.files[0]){
        const reader=new FileReader();
        reader.onload=function(e){ img.src=e.target.result; img.style.display="block"; text.style.display="none"; }
        reader.readAsDataURL(input.files[0]);
    }
}
function deleteImage(){
    const img=document.getElementById("previewImg");
    const text=document.getElementById("noImage");
    const fileInput=document.querySelector('input[name="image"]');
    img.src=""; img.style.display="none"; text.style.display="flex"; fileInput.value="";
}

// AJAX save
const form = document.getElementById('foodForm');

form.addEventListener('submit', function(e){
    e.preventDefault(); // prevent normal form submit

    const formData = new FormData(form);
    formData.append('ajax_save', 1); // flag to indicate AJAX save

    fetch('<?= $_SERVER['PHP_SELF'].'?id='.$food_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            // Update form inputs
            document.querySelector('input[name="name"]').value = data.name;
            document.querySelector('input[name="price"]').value = data.price;

            // Update preview image
            const img = document.getElementById('previewImg');
            const noImg = document.getElementById('noImage');
            if(data.image){
                img.src = data.image;
                img.style.display = 'block';
                noImg.style.display = 'none';
            } else {
                img.style.display = 'none';
                noImg.style.display = 'flex';
            }

            // Show save modal
            document.getElementById('saveModal').style.display = 'flex';

            // Optional: refresh the menu grid if parent window exists
            if(window.opener && typeof window.opener.loadFoods === 'function'){
                window.opener.loadFoods();
            }
        } else {
            alert(data.message); // error from backend
        }
    })
    .catch(err => console.error('AJAX error:', err));
});


function closeSaveModal(){ document.getElementById('saveModal').style.display='none'; }
</script>

</body>
</html>

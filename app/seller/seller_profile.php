<?php
session_start(); echo '<style>.sidebar #logoutLink{margin-top:auto!important;margin-bottom:14px!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar #logoutLink:hover{background:#dc2626!important;color:#fff!important}</style>';
require_once __DIR__ . '/../../config/db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: /Ordering/app/auth/login_register.php");
    exit;
}

$seller_id = $_SESSION['seller_id'];
$errors = [];
$success = "";

function meetsPasswordPolicy($password) {
    return strlen($password) >= 8
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^a-zA-Z\d]/', $password);
}

// FETCH SELLER
$s = $conn->prepare("SELECT full_name, contact, email, password FROM sellers WHERE id=?");
$s->bind_param("i", $seller_id);
$s->execute();
$seller = $s->get_result()->fetch_assoc();
$s->close();

// FETCH CANTEEN
$stmt = $conn->prepare("SELECT id, canteen_name, description, image, is_active FROM canteens WHERE seller_id=? LIMIT 1");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$canteen = $stmt->get_result()->fetch_assoc();
$stmt->close();

// FORM HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['toggle_canteen_status']) && $canteen) {
        $is_active = (int)$_POST['is_active'];
        $status = $conn->prepare("UPDATE canteens SET is_active=? WHERE id=? AND seller_id=?");
        $status->bind_param("iii", $is_active, $canteen['id'], $seller_id);
        $status->execute();
        $status->close();
        header("Location: /Ordering/app/seller/seller_profile.php?success=Canteen+status+updated");
        exit;
    }

    // UPDATE CANTEEN
    if (isset($_POST['update_canteen'])) {

        $name = trim($_POST['canteen_name']);
        $desc = trim($_POST['description']);
        $image_data = $canteen['image'] ?? null;

        if (!empty($_POST['delete_image'])) {
            $image_data = null;
        }

        if (!empty($_FILES['image']['tmp_name'])) {
            $type = mime_content_type($_FILES['image']['tmp_name']);
            if (in_array($type, ['image/jpeg','image/png','image/gif'])) {
                $image_data = file_get_contents($_FILES['image']['tmp_name']);
            } else {
                $errors[] = "Only JPG, PNG or GIF allowed.";
            }
        }

        if (!$errors) {
            if ($canteen) {
                $u = $conn->prepare("UPDATE canteens SET canteen_name=?, description=?, image=? WHERE id=?");
                $u->bind_param("sssi", $name, $desc, $image_data, $canteen['id']);
            } else {
                $u = $conn->prepare("INSERT INTO canteens (seller_id, canteen_name, description, image) VALUES (?,?,?,?)");
                $u->bind_param("isss", $seller_id, $name, $desc, $image_data);
            }
            $u->execute();
            $u->close();
            header("Location: /Ordering/app/seller/seller_profile.php?success=Canteen+updated");
            exit;
        }
    }

    // UPDATE PROFILE
    if (isset($_POST['update_profile'])) {

        $full_name = trim($_POST['full_name']);
        $contact = trim($_POST['contact']);
        $email = trim($_POST['email']);
        $current_pass = $_POST['current_password'] ?? '';
        $p1 = $_POST['new_password'] ?? '';
        $p2 = $_POST['confirm_password'] ?? '';

        if (!$full_name) $errors[] = "Full Name is required.";
        if (!$contact) $errors[] = "Contact Number is required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";

        $c = $conn->prepare("SELECT id FROM sellers WHERE email=? AND id!=?");
        $c->bind_param("si", $email, $seller_id);
        $c->execute();
        $c->store_result();
        if ($c->num_rows > 0) $errors[] = "Email already taken.";
        $c->close();

        if ($p1 || $p2) {
            if (!$current_pass || !password_verify($current_pass, $seller['password'])) {
                $errors[] = "Current password is incorrect.";
            }
            if (!meetsPasswordPolicy($p1)) $errors[] = "New password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.";
            if ($p1 !== $p2) $errors[] = "New passwords do not match.";
        }

        if (!$errors) {
            $u = $conn->prepare("UPDATE sellers SET full_name=?, contact=?, email=? WHERE id=?");
            $u->bind_param("sssi", $full_name, $contact, $email, $seller_id);
            $u->execute();
            $u->close();

            if ($p1) {
                $hashed = password_hash($p1, PASSWORD_DEFAULT);
                $p = $conn->prepare("UPDATE sellers SET password=? WHERE id=?");
                $p->bind_param("si", $hashed, $seller_id);
                $p->execute();
                $p->close();
                $clear = $conn->prepare("DELETE FROM password_reset_otps WHERE user_type='seller' AND user_id=?");
                $clear->bind_param("i", $seller_id);
                $clear->execute();
                $clear->close();
            }

            header("Location: /Ordering/app/seller/seller_profile.php?success=Profile+updated");
            exit;
        }
    }

    // REDIRECT IF ERRORS
    if (!empty($errors)) {
        $errStr = urlencode(implode(', ', $errors));
        header("Location: /Ordering/app/seller/seller_profile.php?error=$errStr");
        exit;
    }
}

// REFRESH DATA
$s = $conn->prepare("SELECT full_name, contact, email FROM sellers WHERE id=?");
$s->bind_param("i", $seller_id);
$s->execute();
$seller = $s->get_result()->fetch_assoc();
$s->close();

$stmt = $conn->prepare("SELECT id, canteen_name, description, image, is_active FROM canteens WHERE seller_id=? LIMIT 1");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$canteen = $stmt->get_result()->fetch_assoc();
$stmt->close();

function get_mime_type($data) {
    if (!$data) return 'image/jpeg';
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($finfo, $data);
    finfo_close($finfo);
    return $mime ?: 'image/jpeg';
}

$savedImage = !empty($canteen['image'])
    ? "data:".get_mime_type($canteen['image']).";base64,".base64_encode($canteen['image'])
    : "https://via.placeholder.com/500x300?text=Canteen+Preview";
?>

<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Dashboard | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
/* ====== Your existing CSS unchanged ====== */
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;;color:#0f172a;display:flex;min-height:100vh;}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10;}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;margin:6px 8px;border-radius:8px;transition:0.15s;}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.main{margin-left:230px;padding:1.5rem;flex:1;display:flex;flex-direction:column;min-height:100vh;}
.main > footer { margin-top:32px; }
.topbar{background:white;padding:1.1rem 1.5rem;border-radius:14px;box-shadow:0 4px 16px rgba(0,0,0,.07);display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.card{background:white;padding:26px;border-radius:16px;box-shadow:0 4px 16px rgba(0,0,0,.07)}
.profile-wrapper{display:grid;grid-template-columns:1.1fr .9fr;gap:24px}
input,textarea{width:100%;padding:14px;border-radius:10px;border:1.5px solid #cbd5e1;margin-bottom:14px;box-sizing:border-box}
textarea{height:130px}
form button{background:#2563eb;color:white;border:none;padding:14px;border-radius:10px;font-weight:600;width:100%;cursor:pointer;margin-top:12px;}
.file-row{display:flex;gap:10px}
.file-btn,.delete-btn{flex:1;padding:13px;border-radius:10px;text-align:center;cursor:pointer;}
.file-btn{background: #2563eb;color:white}
.file-btn:hover{background:#1d4ed8}
.delete-btn{background:#fee2e2;color:#b91c1c;font-weight:600;}
.delete-btn:hover{background:#fecaca}
input[type="file"]{display:none}
.tabs{display:flex;margin-top:8px;margin-bottom:22px;background:#e5e7eb;border-radius:12px;overflow:hidden;}
.tabs button{flex:1;background:none;border:none;padding:12px;font-weight:600;width:auto !important;margin-top:0;}
.tabs button.active{background:#2563eb;color:white}
.tab-panel{display:none}.tab-panel.active{display:block}
.preview img{width:100%;height:260px;object-fit:cover;border-radius:14px}
.profile-wrapper {
    display: flex;
    gap: 24px;
}
.profile-wrapper > div {
    flex: 1;
}
.preview {
    max-width: 400px; /* optional, keeps preview from being too wide */
}
textarea {
    width: 100%;           /* full width of container */
    padding: 14px;         /* same padding as inputs */
    border-radius: 10px;
    border: 1.5px solid #cbd5e1;
    margin-bottom: 14px;
    box-sizing: border-box; /* ensures padding doesn't break width */
    resize: vertical;       /* allow resizing only vertically */
    font-family: inherit;   /* match input font */
    font-size: 14px;
}

.logo-box {width: 100%;padding: 20px 0 5px;text-align: center;border-bottom: 1px solid rgba(255,255,255,0.06);}
.logo-box img {width: 125px;height: auto;border-radius: 12px;object-fit: contain;}
.msg{padding:10px;border-radius:10px;margin-bottom:10px}
.error{background:#fee2e2}.success{background:#d1fae5}
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
@keyframes fadeUp{from {opacity: 0;transform: translateY(15px);} to {opacity: 1;transform: translateY(0);}}

/* ========== FOOTER STYLES ========== */
footer {
  background: #0d131d;
  color: #cbd5e1;
  margin-top: auto;
  margin-left: -1.5rem;
  margin-right: -1.5rem;
  margin-bottom: 0;
  padding: 30px 24px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
  width: calc(100% + 3rem);
  box-sizing: border-box;
}

.footer-content {
  max-width: 900px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 32px;
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

<div class="sidebar">
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>
  <h2>UniCanteen</h2>
  <a href="/Ordering/app/seller/seller_dashboard.php">Food Orders</a>
  <a href="/Ordering/app/seller/seller_seats.php">Manage Tables</a>
  <a href="/Ordering/app/seller/seller_menu.php">Menu & Inventory</a>
  <a href="/Ordering/app/seller/seller_analytics.php">Sales Analytics</a>
  <a href="/Ordering/app/seller/seller_profile.php" class="active">Profile</a>
  <a href="#" id="logoutLink">Logout</a>
</div>

<div class="main">
  <div class="topbar"><h2>Profile Settings</h2></div>
<div class="profile-wrapper">

  <!-- Left column: forms -->
  <div style="display:flex; flex-direction:column; gap:24px;">

    <div class="card">
      <div class="tabs">
        <button class="active" onclick="showTab('canteen',this)">Canteen</button>
        <button onclick="showTab('personal',this)">Personal</button>
      </div>

      <div id="canteen" class="tab-panel active">
        <?php if ($canteen): ?>
        <form method="POST" style="margin-bottom:16px;padding:14px;border-radius:10px;background:<?= $canteen['is_active'] ? '#dcfce7' : '#fee2e2' ?>;">
          <strong style="color:<?= $canteen['is_active'] ? '#166534' : '#b91c1c' ?>;">Canteen is currently <?= $canteen['is_active'] ? 'Open' : 'Closed' ?></strong>
          <input type="hidden" name="is_active" value="<?= $canteen['is_active'] ? 0 : 1 ?>">
          <button class="save-btn" name="toggle_canteen_status" style="background:<?= $canteen['is_active'] ? '#dc2626' : '#16a34a' ?>;"> <?= $canteen['is_active'] ? 'Close Canteen' : 'Open Canteen' ?></button>
        </form>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="delete_image" id="delete_image">
          <label>Canteen Name</label>
          <input type="text" name="canteen_name" required value="<?=htmlspecialchars($canteen['canteen_name']??'')?>">
          <label>Description</label>
          <textarea name="description"><?=htmlspecialchars($canteen['description']??'')?></textarea>
          <div class="file-row">
            <label class="file-btn">Choose Image
              <input type="file" id="imgInput" name="image" accept="image/*">
            </label>
            <div class="delete-btn" onclick="removeImage()">Delete Image</div>
          </div>
          <button class="save-btn" name="update_canteen">Save Canteen</button>
        </form>
      </div>

      <div id="personal" class="tab-panel">
        <form method="POST" name="update_profile_form">
          <label>Full Name</label>
          <input type="text" name="full_name" value="<?=htmlspecialchars($seller['full_name'] ?? '')?>" required>

          <label>Contact Number</label>
          <input type="text" name="contact" value="<?=htmlspecialchars($seller['contact'] ?? '')?>" required>

          <label>Email</label>
          <input type="email" name="email" value="<?=htmlspecialchars($seller['email'] ?? '')?>" required>

        <label>Current Password</label>
<div style="position:relative;">
  <input type="password" id="current_password" name="current_password" 
         placeholder="Enter Current Password" value="********" readonly 
         style="color: #999;">
</div>
<div style="margin-top:8px;">
  <span id="toggleChangePassword" 
        style="cursor:pointer; color:#2563eb; font-size:13px; text-decoration:underline;">
    Change Password
  </span>
</div>

          <div id="newPassContainer" style="display:none; margin-top:10px;">
            <label>New Password</label>
            <input type="password" id="new_password" name="new_password" placeholder="New password" minlength="8" title="Use at least 8 characters, including uppercase, lowercase, a number, and a special character.">
            <label>Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password">
            <small style="color:#f43f5e; display:block;" id="passHint">Use 8+ characters with uppercase, lowercase, a number, and a special character.</small>
          </div>

          <button name="update_profile">Save Personal</button>
        </form>
      </div>

    </div>

  </div>

  <!-- Right column: preview -->
  <div class="card preview">
    <h3>Preview</h3>
    <img id="preview" src="<?=$savedImage?>">
  </div>

<!-- ========== FOOTER ========== -->

</div>


<div id="logoutModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
      <h3>Confirm Logout</h3>
      <p>Logging out will not save the data. Are you sure?</p>
      <div class="uc-modal-buttons">
        <button id="confirmLogout" class="uc-btn yes">Logout</button>
        <button id="cancelLogout" class="uc-btn no">Cancel</button>
      </div>
  </div>
</div>

<div id="messageModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
      <h3 id="msgTitle"></h3>
      <p id="msgBody"></p>
      <div class="uc-modal-buttons">
          <button id="msgClose" class="uc-btn yes">OK</button>
      </div>
  </div>
</div>

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

<script>
const imgInput = document.getElementById("imgInput");
const preview = document.getElementById("preview");
const savedImage = "<?=$savedImage?>";
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');

// ===== Message modal =====
const messageModal = document.getElementById('messageModal');
const msgTitle = document.getElementById('msgTitle');
const msgBody  = document.getElementById('msgBody');
const msgClose = document.getElementById('msgClose');
msgClose.onclick = () => messageModal.style.display = 'none';
messageModal.addEventListener('click', e => { if(e.target === messageModal) messageModal.style.display = 'none'; });

// ===== Show success/error from GET params =====
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const success = params.get('success');
    const error = params.get('error');
    if (success) showMessageModal('Success', success);
    if (error) showMessageModal('Error', error);
    if (success || error) window.history.replaceState({}, document.title, window.location.pathname);
});

function showMessageModal(title, message){
    msgTitle.textContent = title;
    msgBody.textContent = message;
    messageModal.style.display = 'flex';
}

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
  if (e.target === logoutModal) logoutModal.style.display = 'none';
});

imgInput.onchange = e => {
  const file = e.target.files[0];
  if (file) preview.src = URL.createObjectURL(file);
};

function removeImage(){
  imgInput.value = "";
  document.getElementById("delete_image").value = "1";
  preview.src = savedImage.includes("placeholder") ? savedImage : "https://via.placeholder.com/500x300?text=Canteen+Preview";
}

function showTab(tab,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove("active"));
  document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove("active"));
  document.getElementById(tab).classList.add("active");
  btn.classList.add("active");
}

const toggleLink = document.getElementById('toggleChangePassword');
const currentPasswordInput = document.getElementById('current_password');
const newPassContainer = document.getElementById('newPassContainer');
const newPassInput = document.getElementById('new_password');
const confirmPassInput = document.getElementById('confirm_password');
const passHint = document.getElementById('passHint');

toggleLink.addEventListener('click', () => {
    const isOpen = newPassContainer.style.display === 'block';

    if (!isOpen) {
        currentPasswordInput.removeAttribute('readonly');
        currentPasswordInput.value = '';
        currentPasswordInput.focus();
        newPassContainer.style.display = 'block';
        toggleLink.textContent = 'Cancel Password Change';
        passHint.textContent = 'Use 8+ characters with uppercase, lowercase, a number, and a special character.';
        passHint.style.color = '#f43f5e';
    } else {
        currentPasswordInput.setAttribute('readonly', 'readonly');
        currentPasswordInput.value = '********';
        newPassContainer.style.display = 'none';
        toggleLink.textContent = 'Change Password';
        newPassInput.value = '';
        confirmPassInput.value = '';
        passHint.textContent = 'Use 8+ characters with uppercase, lowercase, a number, and a special character.';
        passHint.style.color = '#f43f5e';
    }
});


// Client-side password policy guidance; the server enforces the same policy.
document.querySelector('form[name="update_profile_form"]').addEventListener('submit', function(e){
    if(newPassContainer.style.display === 'block'){
        const newPass = newPassInput.value;
        const meetsPolicy = newPass.length >= 8 && /[a-z]/.test(newPass) &&
            /[A-Z]/.test(newPass) && /\d/.test(newPass) && /[^a-zA-Z\d]/.test(newPass);
        if(!meetsPolicy){
            e.preventDefault();
            passHint.textContent = "Use 8+ characters with uppercase, lowercase, a number, and a special character.";
            passHint.style.color = "#f43f5e";
            newPassInput.focus();
        }
    }
});
</script>

</body>
</html>

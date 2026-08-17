<?php
session_start(); echo '<style>.sidebar #logoutLink{margin-top:auto!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar #logoutLink:hover{background:#dc2626!important;color:#fff!important}</style>';
require_once __DIR__ . '/../../config/db_connection.php';

// Determine user type
if (isset($_SESSION['student_id'])) {
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
    $table = 'students';
    $id_col = 'student_id';
} elseif (isset($_SESSION['seller_id'])) {
    $user_type = 'seller';
    $user_id = $_SESSION['seller_id'];
    $table = 'sellers';
    $id_col = 'seller_id';
} else {
    header("Location: /Ordering/app/auth/login_register.php");
    exit;
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM $table WHERE $id_col = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Handle success/error from URL parameters
$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';
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
:root {
  --sidebar-bg: #1e293b;
  --accent: #2563eb;
  --accent-hover: #1d4ed8;
  --text-dark: #0f172a;
  --text-muted: #64748b;
  --card-bg: #ffffff;
  --rounded: 14px;
  --shadow: 0 6px 18px rgba(0,0,0,0.10);
}
*{box-sizing:border-box}
html{height:100%}
body{margin:0;font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;background:#f8fafc;min-height:100vh;display:flex;flex-direction:column;color:#0f172a}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;transition:0.15s;border-radius:8px;margin:6px 8px}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.content-wrapper{margin-left:230px;display:flex;flex-direction:column;min-height:100vh;flex:1;}
.main{padding:1.5rem;flex:1;margin-bottom:0;}
.topbar {background: white;padding: 1.4rem 2rem;border-radius: var(--rounded);margin-bottom: 1rem;box-shadow: 0 2px 8px rgba(0,0,0,0.06);display: flex;justify-content: space-between;align-items: center;}
.topbar h3 { margin: 0; font-family: Montserrat; }
.profile-header {background: white;padding: 2rem;border-radius: var(--rounded);box-shadow: var(--shadow);display: flex;align-items: center;gap: 30px;justify-content: space-between;margin-bottom: 1rem;}
.user-icon {width: 120px;height: 120px;border-radius: 50%;background: #cbd5e1;display: flex;align-items: center;justify-content: center;flex-shrink: 0;}
.user-icon svg {width: 70px;height: 70px;fill: white;}
.profile-info {flex: 1;margin-left: 0px;}
.profile-info h3 {margin: 0;font-size: 1.8rem;font-family: Montserrat;}
.profile-info p {margin: 6px 0 16px;color: var(--text-muted);font-size: 1rem;}
.header-buttons {display: flex;gap: 10px;}
.header-buttons button {padding: 10px 20px;border-radius: 10px;font-weight: 500;cursor: pointer;border: none;font-size: 0.95rem;}
.edit-btn { background: var(--accent); color: #fff; }
.edit-btn:hover { background: var(--accent-hover); }
.cancel-btn {background:#dc2626;color: #fff;border: none;padding: 14px;border-radius: 10px;font-weight:600;cursor: pointer;transition: 0.2s;}
.cancel-btn:hover {background: #b91c1c;}
#editBanner {display: none;background: #fef3c7;padding: 10px;border-radius: 8px;margin-bottom: 1rem;color: #92400e;font-weight: 500;text-align: center;}
.profile-grid {display: grid;grid-template-columns: 1fr 1fr;gap: 2rem;flex-wrap: wrap;}
.card {background: white;padding: 2rem;border-radius: var(--rounded);box-shadow: var(--shadow);transition: background 0.3s, border 0.3s;}
.card.edit-mode {background: #fef9f5;border: 1px solid var(--accent);}
form h3 { margin-top: 0; margin-bottom: 1.4rem; font-family: Montserrat; font-size: 1.25rem; }
.form-group { margin-bottom: 1.3rem; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; }
.form-group input {width: 100%;max-width: 100%;padding: 12px 14px;border-radius: 12px;border: 1px solid #cbd5e1;background: #f8faff;font-size: 1rem;}
.form-group input:focus {border-color: var(--accent);outline: none;box-shadow: 0 0 0 2px rgba(37,99,235,0.2);}
.password-container {position: relative;}
.password-container input {padding-right: 42px;}
.form-footer {display: none;margin-top: 1rem;text-align: right;}
.save-btn {background: var(--accent);border: none;color: white;padding: 14px;border-radius: 12px;cursor: pointer;font-weight:600;width: auto;margin-left: 10px;font-size: 1rem;}
.save-btn:disabled { background: #94a3b8; cursor: not-allowed;}
.save-btn:hover:enabled { background: var(--accent-hover);}
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
/* Loading Overlay */
#loadingOverlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000000;
}
#loadingOverlay .spinner {
    border: 6px solid #f3f3f3;
    border-top: 6px solid #2563eb;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes fadeUp{
  from {opacity: 0;transform: translateY(15px);}
  to {opacity: 1;transform: translateY(0);}
}

/* ========== FOOTER STYLES ========== */
footer {
  background: #0d131d;
  color: #cbd5e1;
  margin-top: auto;
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
    <!-- Loading Overlay -->
<div id="loadingOverlay">
    <div class="spinner"></div>
</div>


<div class="sidebar">
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>
  <h2>UniCanteen</h2>
  <a href="/Ordering/app/student/student_dashboard.php">Canteen & Reservation</a>
  <a href="/Ordering/app/general/my_orders.php">My Orders</a>
  <a href="/Ordering/app/general/profile.php" class="active">Profile</a>
  <a href="#" id="logoutLink">Logout</a>
</div>

<div class="content-wrapper">
  <div class="main">
    <div class="topbar"><h3>Profile</h3></div>
    <div id="editBanner">You are now editing your profile</div>

    <div class="profile-header">
        <div class="user-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.2c-3.1 0-9.2 1.6-9.2 4.9v2.2h18.4v-2.2c0-3.3-6.1-4.9-9.2-4.9z"/>
            </svg>
        </div>
        <div class="profile-info">
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <p><?= $user_type==='student' ? "School ID: ".htmlspecialchars($user['student_id']) : "Seller ID: ".htmlspecialchars($user['seller_id']); ?></p>
        </div>
        <div class="header-buttons">
            <button class="edit-btn" onclick="toggleEdit(true)">Edit Profile</button>
            <button id="changePasswordBtn" class="edit-btn" onclick="togglePasswordEdit(true)">Change Password</button>
        </div>
    </div>

    <div class="profile-grid">
        <form class="card" id="updateForm" method="POST" action="/Ordering/app/general/actions/update_profile.php">
            <h3>Personal Information</h3>
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" id="name" value="<?= htmlspecialchars($user['full_name']) ?>" disabled></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
            <div class="form-group"><label>Contact Number</label><input type="text" name="contact" id="contact" value="<?= htmlspecialchars($user['contact']) ?>" disabled></div>
            <div class="form-footer">
                <button class="cancel-btn" type="button" onclick="toggleEdit(false)">Cancel</button>
                <button class="save-btn" type="submit" disabled>Save Changes</button>
            </div>
        </form>

        <form class="card" id="passwordForm" method="POST" action="/Ordering/app/general/actions/update_password.php">
            <h3>Change Password</h3>
            <div class="form-group password-container"><label>Current Password</label><input type="password" name="current_pass" id="current_pass" placeholder="Enter current password" disabled required></div>
            <div class="form-group password-container"><label>New Password</label><input type="password" name="new_pass" id="new_pass" placeholder="Enter new password" minlength="8" title="Use at least 8 characters, including uppercase, lowercase, a number, and a special character." disabled required></div>
            <div class="form-group password-container"><label>Confirm New Password</label><input type="password" name="confirm_pass" id="confirm_pass" placeholder="Confirm new password" disabled required></div>
            <small id="passwordWarning" style="display:none;color:#be123c;font-weight:600;">Weak password: use 8+ characters with uppercase, lowercase, a number, and a special character.</small>
            <div class="form-footer" style="text-align: right;">
                <button type="button" class="cancel-btn" onclick="togglePasswordEdit(false)">Cancel</button>
                <button type="submit" class="save-btn" disabled>Save Changes</button>
            </div>
        </form>
    </div>

    </div>

<!-- Modal templates -->
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

<div id="unsavedModal" class="uc-modal-overlay">
    <div class="uc-modal-box">
        <h3>Unsaved Changes</h3>
        <p>Logging out will not save your data.</p>
        <div class="uc-modal-buttons">
            <button id="unsavedContinue" class="uc-btn yes">Continue</button>
            <button id="unsavedCancel" class="uc-btn no">Stay</button>
        </div>
    </div>
</div>

<div id="cancelEditModal" class="uc-modal-overlay">
    <div class="uc-modal-box">
        <h3>Discard Changes?</h3>
        <p>If you leave now, your changes will not be saved.</p>
        <div class="uc-modal-buttons">
            <button id="discardChanges" class="uc-btn yes">Discard</button>
            <button id="keepEditing" class="uc-btn no">Keep Editing</button>
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

<script>
// ===== Editing logic (profile & password) =====
let isEditing = false;
function setEditing(state){ isEditing = state; }

const formInputs = document.querySelectorAll('#updateForm input[type=text], #updateForm input[type=email]');
const saveBtn = document.querySelector('#updateForm .save-btn');
const formFooter = document.querySelector('#updateForm .form-footer');
const editBanner = document.getElementById('editBanner');

const passwordForm = document.getElementById('passwordForm');
const pwInputs = passwordForm.querySelectorAll('input[type=password]');
const pwSaveBtn = passwordForm.querySelector('.save-btn');
const pwFormFooter = passwordForm.querySelector('.form-footer');
const changePasswordBtn = document.getElementById('changePasswordBtn');

let originalValues = {};
formInputs.forEach(i => originalValues[i.id] = i.value);

function toggleEdit(enable){
    if(enable){
        setEditing(true);
        formInputs.forEach(input => input.disabled = false);
        saveBtn.disabled = true;
        editBanner.style.display = 'block';
        document.getElementById('updateForm').classList.add('edit-mode');
        formFooter.style.display = 'block';
    } else {
        let changed = Array.from(formInputs).some(i => i.value !== originalValues[i.id]);
        if(changed) return showCancelEditModal("profile");
        setEditing(false);
        formInputs.forEach(input => input.value = originalValues[input.id]);
        formInputs.forEach(input => input.disabled = true);
        saveBtn.disabled = true;
        editBanner.style.display = 'none';
        document.getElementById('updateForm').classList.remove('edit-mode');
        formFooter.style.display = 'none';
    }
}
formInputs.forEach(input => input.addEventListener('input', ()=>{
    saveBtn.disabled = !Array.from(formInputs).some(i => i.value !== originalValues[i.id]);
}));

function togglePasswordEdit(enable){
    if(enable){
        setEditing(true);
        pwInputs.forEach(input => input.disabled = false);
        pwSaveBtn.disabled = true;
        editBanner.style.display = 'block';
        passwordForm.classList.add('edit-mode');
        pwFormFooter.style.display = 'block';
    } else {
        let changed = Array.from(pwInputs).some(i => i.value.trim() !== "");
        if(changed) return showCancelEditModal("password");
        setEditing(false);
        pwInputs.forEach(input => input.value = '');
        pwInputs.forEach(input => input.disabled = true);
        pwSaveBtn.disabled = true;
        editBanner.style.display = 'none';
        passwordForm.classList.remove('edit-mode');
        pwFormFooter.style.display = 'none';
    }
}
pwInputs.forEach(input => input.addEventListener('input', ()=>{
    pwSaveBtn.disabled = !Array.from(pwInputs).some(i => i.value.trim() !== '');
    const newPassword = document.getElementById('new_pass').value;
    const weak = newPassword.length > 0 && !(newPassword.length >= 8 && /[a-z]/.test(newPassword) && /[A-Z]/.test(newPassword) && /\d/.test(newPassword) && /[^a-zA-Z\d]/.test(newPassword));
    document.getElementById('passwordWarning').style.display = weak ? 'block' : 'none';
}));

function togglePassword(icon) {
    const input = icon.previousElementSibling;
    const svg = icon.querySelector('svg');
    if (!input || !svg) return;
    if (input.type === 'password') {
        input.type = 'text';
        svg.innerHTML = '<path d="M2.99902 3L20.999 21M9.8433 9.91364C9.32066 10.4536 8.99902 11.1892 8.99902 12C8.99902 13.6569 10.3422 15 11.999 15C12.8215 15 13.5667 14.669 14.1086 14.133M6.49902 6.64715C4.59972 7.90034 3.15305 9.78394 2.45703 12C3.73128 16.0571 7.52159 19 11.9992 19C13.9881 19 15.8414 18.4194 17.3988 17.4184M10.999 5.04939C11.328 5.01673 11.6617 5 11.9992 5C16.4769 5 20.2672 7.94291 21.5414 12C21.2607 12.894 20.8577 13.7338 20.3522 14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    } else {
        input.type = 'password';
        svg.innerHTML = '<path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>';
    }
}

// ===== Logout modals =====
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');

const unsavedModal = document.getElementById('unsavedModal');
const unsavedContinue = document.getElementById('unsavedContinue');
const unsavedCancel = document.getElementById('unsavedCancel');
function showLogoutLoadingAndRedirect(url){
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';
    setTimeout(() => {
        window.location.href = url;
    }, 2000); // 2 seconds loading
}

// Logout event handlers
logoutLink.addEventListener("click", function(e){
    e.preventDefault();
    if(isEditing) unsavedModal.style.display='flex';
    else logoutModal.style.display='flex';
});

unsavedContinue.onclick = ()=> showLogoutLoadingAndRedirect('/Ordering/app/auth/logout.php');
unsavedCancel.onclick = ()=> unsavedModal.style.display='none';

confirmBtn.onclick = ()=> showLogoutLoadingAndRedirect('/Ordering/app/auth/logout.php');
cancelBtn.onclick = ()=> logoutModal.style.display='none';

// ===== Cancel editing modal =====
const cancelEditModal = document.getElementById('cancelEditModal');
let cancelTarget = "";
const discardBtn = document.getElementById('discardChanges');
const keepEditingBtn = document.getElementById('keepEditing');
function showCancelEditModal(type){ cancelTarget=type; cancelEditModal.style.display='flex'; }
discardBtn.onclick = ()=>{
    cancelEditModal.style.display='none';
    if(cancelTarget==="profile"){
        toggleEdit(false);
    } else if(cancelTarget==="password"){
        setEditing(false);
        pwInputs.forEach(input => input.value = '');
        pwInputs.forEach(input => input.disabled = true);
        pwSaveBtn.disabled = true;
        editBanner.style.display = 'none';
        passwordForm.classList.remove('edit-mode');
        pwFormFooter.style.display = 'none';
    }
};
keepEditingBtn.onclick = ()=> cancelEditModal.style.display='none';

// ===== Message modal =====
const messageModal = document.getElementById('messageModal');
const msgTitle = document.getElementById('msgTitle');
const msgBody  = document.getElementById('msgBody');
const msgClose = document.getElementById('msgClose');
msgClose.onclick = ()=> messageModal.style.display='none';


// ------------------------
// Show success/error modals
// ------------------------
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');

    if (success) {
        let msg = '';
        if (success === 'profile') msg = 'Profile updated successfully!';
        if (success === 'password') msg = 'Password updated successfully!';
        showCustomModal('Success', msg);
    }

    if (error) {
        showCustomModal('Error', decodeURIComponent(error.replace(/\+/g, ' ')));
    }

    // Remove GET parameters so modal doesn't show again on refresh
    if (success || error) {
        const cleanUrl = window.location.origin + window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }
});

// Function to show modal like your logout modal
function showCustomModal(title, message){
    const modal = document.createElement('div');
    modal.className = 'uc-modal-overlay';
    modal.innerHTML = `
        <div class="uc-modal-box">
            <h3>${title}</h3>
            <p>${message}</p>
            <div class="uc-modal-buttons">
                <button class="uc-btn yes">OK</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    modal.querySelector('button').addEventListener('click', () => {
        modal.style.display = 'none';
        modal.remove();
    });
    modal.addEventListener('click', e => {
        if(e.target === modal){
            modal.style.display = 'none';
            modal.remove();
        }
    });
}

</script>

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

</body>
</html>



svg

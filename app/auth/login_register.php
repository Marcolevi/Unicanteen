<?php
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

date_default_timezone_set('Asia/Manila'); // ensure timezone matches

// ---------------------------
// Initialize variables
// ---------------------------
$errors = [];
$success = "";
$id_input = '';
$full_name_input = '';
$email_input = '';
$contact_input = '';
$user_type = 'student';

function meetsPasswordPolicy($password) {
    $simplePassword = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $password));
    $commonPasswords = ['password', 'password1', 'password123', '12345678', 'qwerty', 'qwerty123', 'admin', 'admin123', 'welcome', 'welcome123', 'iloveyou'];
    return strlen($password) >= 8
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^a-zA-Z\d]/', $password)
        && !in_array($simplePassword, $commonPasswords, true)
        && !preg_match('/^(.)\1+$/', $password);
}

// ---------------------------
// Handle Registration
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $user_type = $_POST['user_type'] ?? 'student';
    $id_input = trim($_POST['id']);
    $full_name_input = trim($_POST['full_name']);
    $email_input = trim($_POST['email']);
    $contact_input = trim($_POST['contact']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) $errors[] = "Passwords do not match!";
    if (!meetsPasswordPolicy($password)) $errors[] = "Password must be at least 8 characters, include uppercase, lowercase, a number and a special character, and must not be a common password.";
    if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format!";

    if (!$errors) {
        if ($user_type === 'student') {
            $dashboard = '/Ordering/app/student/student_dashboard.php';
            $stmt = $conn->prepare("SELECT id FROM students WHERE student_id=? OR email=?");
            $stmt->bind_param("ss", $id_input, $email_input);
        } else {
            $dashboard = '/Ordering/app/seller/seller_dashboard.php';
            $stmt = $conn->prepare("SELECT id FROM sellers WHERE email=?");
            $stmt->bind_param("s", $email_input);
        }
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "School ID or Email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            require_once __DIR__ . '/../../config/mailer.php';
            $otp = (string)random_int(100000, 999999);
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 300);
            $resend = date('Y-m-d H:i:s', time() + 60);
            $conn->query("DELETE FROM pending_registrations WHERE email='" . $conn->real_escape_string($email_input) . "'");
            $pending = $conn->prepare("INSERT INTO pending_registrations (user_type,user_identifier,full_name,email,contact,password_hash,otp_hash,otp_expires_at,resend_available_at) VALUES (?,?,?,?,?,?,?,?,?)");
            $pending->bind_param("sssssssss", $user_type, $id_input, $full_name_input, $email_input, $contact_input, $hashed_password, $otpHash, $expires, $resend);
            $pending->execute();
            $_SESSION['pending_registration_id'] = $pending->insert_id;
            try { sendVerificationOtp($email_input, $full_name_input, $otp); header('Location: /Ordering/app/auth/verify_email.php'); exit; } catch (Throwable $e) { $conn->query('DELETE FROM pending_registrations WHERE id='.(int)$pending->insert_id); $errors[]='Unable to send verification email. Please try again later.'; }
        }
    }
}

// ---------------------------
// Handle Login
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $user_type = $_POST['user_type'] ?? 'student';
    $id_input = trim($_POST['id']);
    $password = $_POST['password'] ?? '';

    if ($user_type === 'student') {
        $dashboard = '/Ordering/app/student/student_dashboard.php';
        $stmt = $conn->prepare("SELECT student_id, full_name, password FROM students WHERE student_id=?");
        $stmt->bind_param("s", $id_input);
    } else {
        $dashboard = '/Ordering/app/seller/seller_dashboard.php';
        $stmt = $conn->prepare("SELECT id, full_name, password, approval_status FROM sellers WHERE email=?");
        $stmt->bind_param("s", $id_input);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            if ($user_type === 'seller' && ($row['approval_status'] ?? 'pending') !== 'approved') {
                $errors[] = 'Your seller account is awaiting administrator approval.';
            } else {
            if ($user_type === 'student') $_SESSION['student_id'] = $row['student_id'];
            else $_SESSION['seller_id'] = $row['id'];

            $_SESSION['full_name'] = $row['full_name'];
            header("Location: $dashboard");
            exit;
            }
        } else $errors[] = "Incorrect password.";
    } else {
        $errors[] = $user_type === 'student' ? "School ID not found." : "Email not found.";
    }
}

// ---------------------------
// Handle Forgot Password
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot') {
    $user_type = ($_POST['user_type'] ?? 'student') === 'seller' ? 'seller' : 'student';
    $id_or_email = trim($_POST['id_or_email']);

    if ($user_type === 'student') {
        $stmt = $conn->prepare("SELECT id, full_name, email FROM students WHERE student_id=? LIMIT 1");
        $stmt->bind_param("s", $id_or_email);
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email FROM sellers WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $id_or_email);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        require_once __DIR__ . '/../../config/mailer.php';
        $existing = $conn->prepare('SELECT id, resend_available_at FROM password_reset_otps WHERE user_type=? AND user_id=? ORDER BY id DESC LIMIT 1');
        $existing->bind_param('si', $user_type, $row['id']);
        $existing->execute();
        $activeRequest = $existing->get_result()->fetch_assoc();
        $existing->close();
        if ($activeRequest && strtotime($activeRequest['resend_available_at']) > time()) {
            $errors[] = 'A reset code was recently requested. Please wait one minute before trying again.';
        } else {
        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 300);
        $resendAt = date('Y-m-d H:i:s', time() + 60);

        $delete = $conn->prepare('DELETE FROM password_reset_otps WHERE user_type=? AND user_id=?');
        $delete->bind_param('si', $user_type, $row['id']);
        $delete->execute();

        $insert = $conn->prepare('INSERT INTO password_reset_otps (user_type, user_id, email, otp_hash, otp_expires_at, resend_available_at) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->bind_param('sissss', $user_type, $row['id'], $row['email'], $otpHash, $expires, $resendAt);
        $insert->execute();

        try {
            sendPasswordResetOtp($row['email'], $row['full_name'], $otp);
            $_SESSION['password_reset_otp_id'] = $insert->insert_id;
            header('Location: /Ordering/app/auth/reset_password_otp.php');
            exit;
        } catch (Throwable $e) {
            $cleanup = $conn->prepare('DELETE FROM password_reset_otps WHERE id=?');
            $cleanup->bind_param('i', $insert->insert_id);
            $cleanup->execute();
            $errors[] = 'Unable to send the reset code. Please try again later.';
        }
        }
    } else {
        $success = 'If an account matches those details, a reset code will be sent to its registered email address.';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | UniCanteen</title>

<style>
body{font-family:Poppins,sans-serif;background:#f0f4f8;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
.container{background:#fff;padding:2rem;border-radius:15px;box-shadow:0 10px 25px rgba(0,0,0,0.1);width:360px;max-width:90%;text-align:center}
.logo-container{width:125px;margin:auto}.logo-container img{width:100%}
h2{margin:12px 0}
.errors{color:red;margin-bottom:10px}
.user-type-buttons{display:flex;gap:10px;margin-bottom:15px}
.user-type-buttons button{flex:1;padding:12px;border:none;border-radius:8px;background:#e2e8f0;cursor:pointer;font-weight:500}
.user-type-buttons button.active{background:#2563eb;color:white}
form{display:flex;flex-direction:column;gap:12px}
input,.submit-btn{width:100%;box-sizing:border-box;padding:10px;border-radius:8px;border:1px solid #cbd5e1;font-size:14px}
.password-container{position:relative}
.eye-icon{position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;width:20px;height:20px;opacity:0.7}
.eye-icon:hover{opacity:1}
.submit-btn{background:#2563eb;color:white;border:none;font-weight:bold;cursor:pointer}
.register-link{margin-top:10px}.register-link a{color:#2563eb;text-decoration:none}
#registerBox,#forgotBox{display:none;}
.password-checklist{text-align:left;font-size:12px;color:#64748b;margin:-4px 0 2px}.password-checklist span{display:block;margin:3px 0}.password-checklist .met{color:#15803d}.password-checklist .met::before{content:'✓ ';font-weight:bold}.password-checklist .unmet::before{content:'• ';}


/* ===== Modal ===== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;justify-content:center;align-items:center;z-index:99999;backdrop-filter:blur(3px)}
.modal-box{background:#fff;width:90%;max-width:400px;padding:25px;border-radius:14px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:fadeUp 0.25s ease-out;word-wrap:break-word}
.modal-box h3{margin:0 0 8px;font-size:20px;font-weight:700;color:#1e293b}
.modal-box p{margin:0 0 20px;font-size:15px;color:#475569;word-break:break-word}
.modal-buttons{display:flex;justify-content:flex-end;gap:10px}
.modal-btn{padding:10px 15px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-weight:600;transition:0.2s}
.modal-btn.no{background: #e2e8f0;color: #1e293b}
.modal-btn.no:hover{background: #cbd5e1}
.modal-btn.yes{background: #2563eb; color: white;}
.modal-btn.yes:hover{background: #1d4ed8;}
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

@keyframes fadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}


</style>
</head>
<body>
    <!-- Loading Overlay -->
<div id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Toast Notification -->
<div id="toast" style="
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: #000000;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 500;
    display: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    z-index: 100000;
    transition: opacity 0.3s ease, transform 0.3s ease;
">Copied to clipboard!</div>


<div class="container">
<div class="logo-container">
<img src="assets/images/logo2.png">
</div>
<h2>UniCanteen</h2>

<?php if($errors) echo "<div class='errors'>".implode("<br>",$errors)."</div>"; ?>
<?php if($success): ?><div class="errors" style="color:#166534;background:#f0fdf4;border-color:#bbf7d0;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (($_GET['password_reset'] ?? '') === 'success'): ?><div class="errors" style="color:#15803d;background:#f0fdf4;border-color:#bbf7d0;">Password reset successful. You can now log in.</div><?php endif; ?>

<!-- LOGIN FORM -->
<div id="loginBox">
<div class="user-type-buttons">
<button id="loginStudent" class="active" onclick="setLogin('student')">Customer</button>
<button id="loginSeller" onclick="setLogin('seller')">Seller</button>
</div>
<form method="POST">
<input type="hidden" name="action" value="login">
<input type="hidden" name="user_type" id="loginType" value="student">
<input id="loginId" name="id" placeholder="School ID" inputmode="numeric" required>
<input type="password" name="password" placeholder="Password" required>
<button class="submit-btn">Login</button>
</form>
<div style="margin-top:5px; text-align:right;">
<a href="#" onclick="showForgotPassword()" style="color:#2563eb; font-size:13px;">Forgot Password?</a>
</div>
<div class="register-link">
Don't have an account? <a href="#" onclick="showRegister()">Register here</a>
</div>
</div>

<!-- REGISTER FORM -->
<div id="registerBox">
<div class="user-type-buttons">
<button id="regStudent" class="active" onclick="setRegister('student')">Customer</button>
<button id="regSeller" onclick="setRegister('seller')">Seller</button>
</div>
<form method="POST">
<input type="hidden" name="action" value="register">
<input type="hidden" name="user_type" id="regType" value="student">
<input id="studentID" name="id" placeholder="School ID" inputmode="numeric">
<input name="full_name" placeholder="Full Name" required>
<input name="email" placeholder="Email" required>
<input name="contact" placeholder="Contact">
<input type="password" id="regPassword" name="password" placeholder="Password" minlength="8" title="Use at least 8 characters, including uppercase, lowercase, a number, and a special character." required>
<input type="password" id="regConfirmPassword" name="confirm_password" placeholder="Confirm Password" required>
<div class="password-checklist" id="passwordChecklist" aria-live="polite"><span data-rule="length">Use at least 8 characters</span><span data-rule="lower">Include a lowercase letter</span><span data-rule="upper">Include an uppercase letter</span><span data-rule="number">Include a number</span><span data-rule="special">Include a special character</span><span data-rule="common">Avoid common passwords</span></div>
<button class="submit-btn">Register</button>
</form>
<div class="register-link">
<a href="#" onclick="showLogin()">Back to Login</a>
</div>
</div>

<!-- FORGOT PASSWORD FORM -->
<div id="forgotBox">
<h3>Forgot Password</h3>
<form method="POST" id="forgotForm">
<input type="hidden" name="action" value="forgot">
<input type="hidden" name="user_type" id="forgotType" value="student">
<input id="forgotId" name="id_or_email" placeholder="School ID or Email" required>
<button type="submit" class="submit-btn">Send Reset Code</button>
</form>
<div style="margin-top:8px;">
<a href="#" onclick="showLogin()" style="color:#2563eb; font-size:13px;">Back to Login</a>
</div>
</div>
</div>

<!-- MODAL FOR RESET LINK -->
<div id="linkModal" class="modal-overlay">
  <div class="modal-box">
    <h3>Reset Link</h3>
    <p id="modalLink" style="word-break:break-all;"></p>
    <div class="modal-buttons">
      <button class="modal-btn yes" onclick="copyLink()">Copy Link</button>
      <button class="modal-btn no" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<script>
const loginBox = document.getElementById('loginBox');
const registerBox = document.getElementById('registerBox');
const forgotBox = document.getElementById('forgotBox');
const loginType = document.getElementById('loginType');
const loginId = document.getElementById('loginId');
const studentID = document.getElementById('studentID');
const regType = document.getElementById('regType');
const forgotType = document.getElementById('forgotType');
const forgotId = document.getElementById('forgotId');
const linkModal = document.getElementById('linkModal');
const modalLink = document.getElementById('modalLink');
<?php if($success): ?>
document.addEventListener("DOMContentLoaded", function(){
    modalLink.innerHTML = `<?= $success ?>`;
    linkModal.style.display = 'flex';
});
<?php endif; ?>

function closeModal(){
  linkModal.style.display='none';
  showLogin();
}

// Copy link to clipboard
function copyLink() {
    const link = modalLink.querySelector('a');
    if(link) {
        navigator.clipboard.writeText(link.href).then(() => {
            showToast("Reset link copied to clipboard!");
        }).catch(() => {
            showToast("Failed to copy link. Please copy manually.", true);
        });
    }
}

function togglePassword(icon) {
    const input = icon.previousElementSibling;
    const svg = icon.querySelector('svg');
    if (input.type === 'password') {
        input.type = 'text';
        svg.innerHTML = '<path d="M2.99902 3L20.999 21M9.8433 9.91364C9.32066 10.4536 8.99902 11.1892 8.99902 12C8.99902 13.6569 10.3422 15 11.999 15C12.8215 15 13.5667 14.669 14.1086 14.133M6.49902 6.64715C4.59972 7.90034 3.15305 9.78394 2.45703 12C3.73128 16.0571 7.52159 19 11.9992 19C13.9881 19 15.8414 18.4194 17.3988 17.4184M10.999 5.04939C11.328 5.01673 11.6617 5 11.9992 5C16.4769 5 20.2672 7.94291 21.5414 12C21.2607 12.894 20.8577 13.7338 20.3522 14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    } else {
        input.type = 'password';
        svg.innerHTML = '<path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>';
    }
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.background = isError ? "#ef4444" : "#000000"; // black for success, red for error
    toast.style.display = "block";
    toast.style.opacity = 1;
    toast.style.transform = "translateX(-50%) translateY(0)";
    
    setTimeout(() => {
        toast.style.opacity = 0;
        toast.style.transform = "translateX(-50%) translateY(-20px)";
        setTimeout(() => toast.style.display = "none", 5000);
    }, 2500);
}

function showRegister(){
  loginBox.style.display="none";
  forgotBox.style.display="none";
  registerBox.style.display="block";
}
function showLogin(){
  registerBox.style.display="none";
  forgotBox.style.display="none";
  loginBox.style.display="block";
}
function showForgotPassword(){
  loginBox.style.display="none";
  registerBox.style.display="none";
  forgotBox.style.display="block";
  forgotType.value = loginType.value;
  forgotId.placeholder = loginType.value==="student"?"School ID":"Email";
  forgotId.inputMode = loginType.value === "student" ? "numeric" : "email";
}
function setLogin(type){
  loginType.value = type;
  loginId.placeholder = type==="student"?"School ID":"Email";
  loginId.inputMode = type === "student" ? "numeric" : "email";
  loginStudent.classList.toggle("active",type==="student");
  loginSeller.classList.toggle("active",type==="seller");
}
function setRegister(type){
  regType.value = type;
  studentID.style.display = type==="seller"?"none":"block";
  studentID.inputMode = type === "student" ? "numeric" : "text";
  regStudent.classList.toggle("active",type==="student");
  regSeller.classList.toggle("active",type==="seller");
}

// School IDs use the format 23-20062.  Keep the dash convenient while still
// allowing Backspace/Delete to remove it naturally.
function formatSchoolId(input) {
  const isDeleting = input.dataset.lastInputType === 'deleteContentBackward' ||
    input.dataset.lastInputType === 'deleteContentForward' ||
    input.dataset.lastInputType === 'deleteByCut';
  const digits = input.value.replace(/\D/g, '');

  // On entry, add the separator after the second digit. When deleting back to
  // two digits, leave it out so the user is never trapped behind the dash.
  input.value = digits.length > 2 || (digits.length === 2 && !isDeleting)
    ? `${digits.slice(0, 2)}-${digits.slice(2)}`
    : digits;
}

function isSchoolIdInput(input) {
  return (input === loginId && loginType.value === 'student') ||
    (input === studentID && regType.value === 'student') ||
    (input === forgotId && forgotType.value === 'student');
}

[loginId, studentID, forgotId].forEach((input) => {
  input.addEventListener('beforeinput', (event) => {
    input.dataset.lastInputType = event.inputType;
  });
  input.addEventListener('input', () => {
    if (isSchoolIdInput(input)) formatSchoolId(input);
    delete input.dataset.lastInputType;
  });
});

// Show modal if $success exists
<?php if($success): ?>
document.addEventListener("DOMContentLoaded", function(){
    const modal = document.getElementById('linkModal');
    const modalLink = document.getElementById('modalLink');
    modalLink.innerHTML = `<?= $success ?>`;
    modal.style.display = 'flex';
});
<?php endif; ?>

function closeModal(){
  document.getElementById('linkModal').style.display='none';
  showLogin();
}
// Show loading overlay on login submit and simulate 2s delay
const loginForm = loginBox.querySelector('form');
loginForm.addEventListener('submit', function(e) {
    e.preventDefault(); // prevent immediate submission
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';

    // Simulate 2 seconds loading
    setTimeout(() => {
        loginForm.submit(); // submit form after 2s
    }, 1500);
});

const registerForm = registerBox.querySelector('form');
const regPassword = document.getElementById('regPassword');
const regConfirmPassword = document.getElementById('regConfirmPassword');
const commonPasswords = ['password', 'password1', 'password123', '12345678', 'qwerty', 'qwerty123', 'admin', 'admin123', 'welcome', 'welcome123', 'iloveyou'];

function updatePasswordStrength() {
  const password = regPassword.value;
  const simplePassword = password.toLowerCase().replace(/[^a-z0-9]/g, '');
  const rules = {
    length: password.length >= 8,
    lower: /[a-z]/.test(password),
    upper: /[A-Z]/.test(password),
    number: /\d/.test(password),
    special: /[^a-zA-Z\d]/.test(password),
    common: password.length > 0 && !commonPasswords.includes(simplePassword) && !/^(.)\1+$/.test(password)
  };
  Object.entries(rules).forEach(([rule, met]) => {
    const item = document.querySelector(`[data-rule="${rule}"]`);
    item.classList.toggle('met', met);
    item.classList.toggle('unmet', !met);
  });
  const valid = Object.values(rules).every(Boolean);
  regPassword.setCustomValidity(valid ? '' : 'Use a strong password that meets all listed requirements.');
  regConfirmPassword.setCustomValidity(regConfirmPassword.value && regConfirmPassword.value !== password ? 'Passwords do not match.' : '');
}

regPassword.addEventListener('input', updatePasswordStrength);
regConfirmPassword.addEventListener('input', updatePasswordStrength);
registerForm.addEventListener('submit', updatePasswordStrength);

</script>

<!-- ========== FOOTER ========== -->

</body>
</html>

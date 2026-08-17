<?php
session_start();
require __DIR__ . '/../../config/db_connection.php';
require __DIR__ . '/../../config/mailer.php';
$error = '';
$notice = (($_GET['password_reset'] ?? '') === 'success') ? 'Password reset successful. You can now sign in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'login') === 'forgot') {
    $email = trim($_POST['email'] ?? '');
    $stmt = $conn->prepare('SELECT id, full_name, email FROM admins WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $notice = 'If an administrator account matches that address, a reset code will be sent.';
    if ($admin) {
        $existing = $conn->prepare("SELECT resend_available_at FROM password_reset_otps WHERE user_type='admin' AND user_id=? ORDER BY id DESC LIMIT 1");
        $existing->bind_param('i', $admin['id']);
        $existing->execute();
        $active = $existing->get_result()->fetch_assoc();
        $existing->close();
        if ($active && strtotime($active['resend_available_at']) > time()) {
            $error = 'A reset code was recently requested. Please wait one minute before trying again.';
        } else {
            $otp = (string) random_int(100000, 999999);
            $hash = password_hash($otp, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 300);
            $resend = date('Y-m-d H:i:s', time() + 60);
            $delete = $conn->prepare("DELETE FROM password_reset_otps WHERE user_type='admin' AND user_id=?");
            $delete->bind_param('i', $admin['id']); $delete->execute(); $delete->close();
            $insert = $conn->prepare("INSERT INTO password_reset_otps (user_type,user_id,email,otp_hash,otp_expires_at,resend_available_at) VALUES ('admin',?,?,?,?,?)");
            $insert->bind_param('issss', $admin['id'], $admin['email'], $hash, $expires, $resend);
            $insert->execute();
            try {
                sendPasswordResetOtp($admin['email'], $admin['full_name'], $otp);
                $_SESSION['password_reset_otp_id'] = $insert->insert_id;
                header('Location: /Ordering/app/auth/reset_password_otp.php'); exit;
            } catch (Throwable $e) {
                $cleanup = $conn->prepare('DELETE FROM password_reset_otps WHERE id=?');
                $cleanup->bind_param('i', $insert->insert_id); $cleanup->execute(); $cleanup->close();
                $error = 'Unable to send the reset code. Please try again later.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
    $s = $conn->prepare('SELECT id,full_name,password FROM admins WHERE email=? LIMIT 1');
    $s->bind_param('s', $email); $s->execute(); $a = $s->get_result()->fetch_assoc(); $s->close();
    if ($a && password_verify($password, $a['password'])) { $_SESSION['admin_id']=$a['id']; $_SESSION['full_name']=$a['full_name']; header('Location: /Ordering/app/admin/admin_portal.php'); exit; }
    $error = 'Invalid administrator credentials.';
}
?>
<!doctype html><html><head><base href="/Ordering/"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login | UniCanteen</title><style>body{font-family:Arial;background:#f1f5f9;display:grid;place-items:center;min-height:100vh;margin:0}.box{background:#fff;padding:30px;border-radius:14px;width:min(330px,calc(100% - 32px));box-shadow:0 2px 10px #0002}input,button{box-sizing:border-box;width:100%;padding:11px;margin:7px 0;border-radius:8px;border:1px solid #cbd5e1}button{background:#2563eb;color:#fff;border:0;font-weight:bold;cursor:pointer}.error{color:#b91c1c}.notice{color:#166534}.link{display:block;text-align:right;margin-top:6px;color:#2563eb;font-size:13px;text-decoration:none}#forgot{display:none}</style></head><body><main class="box"><section id="login"><h2>UniCanteen Admin</h2><?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif?><form method="post"><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password" required><button>Sign in</button></form><a class="link" href="#" onclick="showForgot();return false">Forgot Password?</a></section><section id="forgot"><h2>Admin recovery</h2><?php if($notice):?><p class="notice"><?=htmlspecialchars($notice)?></p><?php endif?><form method="post"><input type="hidden" name="action" value="forgot"><input type="email" name="email" placeholder="Administrator email" required><button>Send Reset Code</button></form><a class="link" href="#" onclick="showLogin();return false">Back to login</a></section></main><script>function showForgot(){login.style.display='none';forgot.style.display='block'}function showLogin(){forgot.style.display='none';login.style.display='block'}</script></body></html>

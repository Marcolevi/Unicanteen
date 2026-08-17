<?php
session_start();
require_once 'db_connection.php';
require_once __DIR__ . '/../../config/mailer.php';
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['password_reset_csrf'])) {
    $_SESSION['password_reset_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$message = '';
$resetId = (int) ($_SESSION['password_reset_otp_id'] ?? 0);

function resetPasswordPolicyIsValid($password) {
    return strlen($password) >= 8 && preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password) && preg_match('/[^a-zA-Z\d]/', $password);
}

function getPasswordResetRequest($conn, $id) {
    $stmt = $conn->prepare('SELECT * FROM password_reset_otps WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$request = $resetId ? getPasswordResetRequest($conn, $resetId) : null;
if (!$request) {
    $errors[] = 'Your password reset request is no longer available. Please request a new code.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request) {
    if (!hash_equals($_SESSION['password_reset_csrf'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session has expired. Please request a new code.';
    } elseif (($_POST['action'] ?? '') === 'resend') {
        if (strtotime($request['resend_available_at']) > time()) {
            $errors[] = 'Please wait before requesting another code.';
        } else {
            $otp = (string) random_int(100000, 999999);
            $hash = password_hash($otp, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 300);
            $resendAt = date('Y-m-d H:i:s', time() + 60);
            $stmt = $conn->prepare('UPDATE password_reset_otps SET otp_hash=?, otp_expires_at=?, otp_attempts=0, resend_available_at=? WHERE id=?');
            $stmt->bind_param('sssi', $hash, $expires, $resendAt, $resetId);
            $stmt->execute();
            try {
                sendPasswordResetOtp($request['email'], 'UniCanteen user', $otp);
                $message = 'A new code has been sent. Your previous code is no longer valid.';
                $request = getPasswordResetRequest($conn, $resetId);
            } catch (Throwable $e) {
                $errors[] = 'Unable to send the reset code. Please try again later.';
            }
        }
    } else {
        $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($otp) !== 6) $errors[] = 'Enter the 6-digit code from your email.';
        if (!resetPasswordPolicyIsValid($password)) $errors[] = 'Use 8+ characters with uppercase, lowercase, a number, and a special character.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (strtotime($request['otp_expires_at']) <= time()) $errors[] = 'This code has expired. Request a new one.';
        if ((int)$request['otp_attempts'] >= 5) $errors[] = 'Too many incorrect attempts. Request a new code.';

        if (!$errors && !password_verify($otp, $request['otp_hash'])) {
            $attempts = (int)$request['otp_attempts'] + 1;
            $stmt = $conn->prepare('UPDATE password_reset_otps SET otp_attempts=? WHERE id=?');
            $stmt->bind_param('ii', $attempts, $resetId);
            $stmt->execute();
            $errors[] = $attempts >= 5 ? 'Too many incorrect attempts. Request a new code.' : 'That code is incorrect.';
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $table = ['student' => 'students', 'seller' => 'sellers', 'admin' => 'admins'][$request['user_type']] ?? null;
            if (!$table) {
                $errors[] = 'Invalid password reset request.';
            }
            if (!$errors) {
            $stmt = $conn->prepare("UPDATE $table SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
            $stmt->bind_param('si', $hash, $request['user_id']);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                $delete = $conn->prepare('DELETE FROM password_reset_otps WHERE id=?');
                $delete->bind_param('i', $resetId);
                $delete->execute();
                unset($_SESSION['password_reset_otp_id'], $_SESSION['password_reset_csrf']);
                $redirect = $request['user_type'] === 'admin' ? 'admin_login.php?password_reset=success' : 'login_register.php?password_reset=success';
                header('Location: ' . $redirect);
                exit;
            }
            $errors[] = 'Unable to update the password. Please request a new code.';
            }
        }
    }
}
$resendTimestamp = $request ? strtotime($request['resend_available_at']) * 1000 : 0;
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password | UniCanteen</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f0f4f8;color:#1e293b;font-family:Poppins,Arial,sans-serif}.card{width:min(420px,calc(100% - 32px));box-sizing:border-box;background:#fff;border-radius:18px;padding:32px;box-shadow:0 18px 45px rgba(15,23,42,.12)}h1{margin:0 0 8px;font-size:26px}p{color:#64748b;line-height:1.55}.notice{padding:11px 13px;border-radius:10px;margin:14px 0;font-size:14px}.error{background:#fef2f2;color:#b91c1c}.success{background:#ecfdf5;color:#047857}label{display:block;font-size:13px;font-weight:700;margin:15px 0 6px}input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:12px;font:inherit;outline:none}input:focus{border-color:#2563eb;box-shadow:0 0 0 3px #dbeafe}.otp{letter-spacing:8px;text-align:center;font-size:22px;font-weight:700}button{width:100%;border:0;border-radius:10px;padding:12px;margin-top:20px;background:#2563eb;color:#fff;font:700 15px inherit;cursor:pointer}button:hover{background:#1d4ed8}.resend{margin-top:11px;background:#e2e8f0;color:#334155}.resend:hover{background:#cbd5e1}.resend:disabled{opacity:.65;cursor:not-allowed}a{display:inline-block;margin-top:18px;color:#2563eb;text-decoration:none;font-size:14px}@media(max-width:480px){.card{padding:24px}}</style></head>
<body><main class="card"><h1>Reset your password</h1><p>Enter the 6-digit code sent to your email, then choose a new password. The code expires in 5 minutes.</p>
<?php foreach ($errors as $error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
<?php if ($message): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($request): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['password_reset_csrf']) ?>"><label for="otp">Verification code</label><input class="otp" id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required><label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" required><label for="confirm_password">Confirm new password</label><input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required><button type="submit">Reset Password</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['password_reset_csrf']) ?>"><input type="hidden" name="action" value="resend"><button class="resend" id="resend" type="submit">Resend code</button></form><?php endif; ?><a href="login_register.php">Back to login</a></main><script>const resend=document.getElementById('resend'),readyAt=<?= $resendTimestamp ?>;function update(){if(!resend)return;const left=Math.ceil((readyAt-Date.now())/1000);if(left>0){resend.disabled=true;resend.textContent=`Resend code (${left}s)`;setTimeout(update,250)}else{resend.disabled=false;resend.textContent='Resend code'}}update();</script></body></html>

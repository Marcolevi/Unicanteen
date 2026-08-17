<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';

if (isset($_SESSION['student_id'])) {
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
    $table = 'students';
    $id_col = 'student_id';
} elseif (isset($_SESSION['seller_id'])) {
    $user_type = 'seller';
    $user_id = $_SESSION['seller_id'];
    $table = 'sellers';
    $id_col = 'id';
} else {
    header("Location: /Ordering/app/auth/login_register.php");
    exit;
}

if (!isset($_POST['current_pass'], $_POST['new_pass'], $_POST['confirm_pass'])) {
    header("Location: /Ordering/app/general/profile.php?error=Missing+fields");
    exit;
}

$current_pass = $_POST['current_pass'];
$new_pass     = $_POST['new_pass'];
$confirm_pass = $_POST['confirm_pass'];

$meets_password_policy = strlen($new_pass) >= 8
    && preg_match('/[a-z]/', $new_pass)
    && preg_match('/[A-Z]/', $new_pass)
    && preg_match('/\d/', $new_pass)
    && preg_match('/[^a-zA-Z\d]/', $new_pass);

// Check new password match
if ($new_pass !== $confirm_pass) {
    header("Location: /Ordering/app/general/profile.php?error=Passwords+do+not+match");
    exit;
}
if (!$meets_password_policy) {
    header("Location: /Ordering/app/general/profile.php?error=Password+must+be+at+least+8+characters+and+include+uppercase,+lowercase,+a+number,+and+a+special+character");
    exit;
}

// Fetch user current password
$stmt = $conn->prepare("SELECT password FROM $table WHERE $id_col=?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: /Ordering/app/general/profile.php?error=User+not+found");
    exit;
}

// Verify current password
if (!password_verify($current_pass, $user['password'])) {
    header("Location: /Ordering/app/general/profile.php?error=Incorrect+current+password");
    exit;
}

// Hash new password and update
$hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE $table SET password=? WHERE $id_col=?");
$update->bind_param("ss", $hashed_password, $user_id);

if ($update->execute()) {
    $clear = $conn->prepare('DELETE FROM password_reset_otps WHERE user_type=? AND user_id=?');
    $clear->bind_param('si', $user_type, $user_id);
    $clear->execute();
    $clear->close();
    $update->close();
    $conn->close();
    header("Location: /Ordering/app/general/profile.php?success=password");
    exit;
} else {
    $update->close();
    $conn->close();
    header("Location: /Ordering/app/general/profile.php?error=Update+failed");
    exit;
}
?>

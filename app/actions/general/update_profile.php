<?php
session_start();
include('db_connection.php');

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
    header("Location: login_register.php");
    exit;
}

// Get POST data safely
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$contact   = trim($_POST['contact'] ?? '');

// Validate required fields
if (!$full_name || !$email || !$contact) {
    header("Location: profile.php?error=Please+fill+all+fields");
    exit;
}

// Update user info
$stmt = $conn->prepare("UPDATE $table SET full_name=?, email=?, contact=? WHERE $id_col=?");
$stmt->bind_param("ssss", $full_name, $email, $contact, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    // Redirect with success flag, but use separate redirect to avoid re-show on refresh
    header("Location: profile.php?success=profile");
    exit;
} else {
    $stmt->close();
    $conn->close();
    header("Location: profile.php?error=Update+failed");
    exit;
}
?>

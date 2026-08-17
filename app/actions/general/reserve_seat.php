<?php
session_start();
include 'db_connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

$seat = $_POST['seat'] ?? '';
if (!$seat) {
    echo json_encode(['status'=>'error','msg'=>'No seat selected']);
    exit;
}

// Mark seat as reserved
$stmt = $conn->prepare("UPDATE seats SET is_reserved=1 WHERE seat_number=?");
$stmt->bind_param("s", $seat);
if ($stmt->execute()) {
    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error','msg'=>$stmt->error]);
}
$stmt->close();
$conn->close();
?>

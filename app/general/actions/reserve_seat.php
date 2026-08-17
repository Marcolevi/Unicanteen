<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

$seat = trim($_POST['seat'] ?? '');
$canteenId = (int)($_POST['canteen_id'] ?? 0);
if ($seat === '' || $canteenId <= 0) {
    echo json_encode(['status'=>'error','msg'=>'A canteen and seat are required']);
    exit;
}

$canteen = $conn->prepare('SELECT seller_id FROM canteens WHERE id=? LIMIT 1');
$canteen->bind_param('i', $canteenId);
$canteen->execute();
$row = $canteen->get_result()->fetch_assoc();
$canteen->close();
if (!$row) {
    echo json_encode(['status'=>'error','msg'=>'Canteen not found']);
    exit;
}

// Atomically claim an available seat for this canteen's seller.
$sellerId = (int)$row['seller_id'];
$stmt = $conn->prepare('UPDATE seats SET is_reserved=1 WHERE seller_id=? AND seat_number=? AND is_reserved=0');
$stmt->bind_param('is', $sellerId, $seat);
if (!$stmt->execute()) {
    echo json_encode(['status'=>'error','msg'=>'Unable to reserve the seat']);
} elseif ($stmt->affected_rows !== 1) {
    echo json_encode(['status'=>'error','msg'=>'This seat is no longer available']);
} else {
    echo json_encode(['status'=>'success']);
}
$stmt->close();
$conn->close();
?>

<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['seller_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in as seller']);
    exit;
}

$seller_id = intval($_SESSION['seller_id']);
$seat_id = isset($_POST['seat_id']) ? intval($_POST['seat_id']) : 0;

if (!$seat_id) {
    echo json_encode(['status' => 'error', 'msg' => 'Missing seat ID']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM seats WHERE id=? AND seller_id=?");
$stmt->bind_param("ii", $seat_id, $seller_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'msg' => 'Seat deleted']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Delete failed']);
}

$stmt->close();
$conn->close();
?>

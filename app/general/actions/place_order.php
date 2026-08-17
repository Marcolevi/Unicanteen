<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
require_once __DIR__ . '/../../services/EmailNotificationService.php';

header('Content-Type: application/json; charset=UTF-8');

function json_exit($arr) {
    echo json_encode($arr);
    exit;
}

// Must be logged in
if (!isset($_SESSION['student_id'])) {
    json_exit(['status'=>'error','msg'=>'Not logged in']);
}

$student_id = $_SESSION['student_id'];

// Resolve numeric student id
$stmt = $conn->prepare("SELECT id FROM students WHERE id=? OR student_id=? LIMIT 1");
$stmt->bind_param("is", $student_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) json_exit(['status'=>'error','msg'=>'Student not found']);
$student_db_id = (int)$res->fetch_assoc()['id'];
$stmt->close();

// POST DATA from JS
$mode         = $_POST['mode'] ?? '';
$seat_numbers = $_POST['seat_numbers'] ?? '';
$time_slot    = $_POST['time_slot'] ?? '';

if ($mode === '') {
    json_exit(['status'=>'error','msg'=>'Order mode missing']);
}

// Find user's cart (pending order)
$stmt = $conn->prepare("
    SELECT id FROM orders 
    WHERE student_id=? AND status='new' 
    LIMIT 1
");
$stmt->bind_param("i", $student_db_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    json_exit(['status'=>'error','msg'=>'No draft order']);
}

$order_id = (int)$res->fetch_assoc()['id'];
$stmt->close();

// Update order → set it as final
$stmt = $conn->prepare("
    UPDATE orders
    SET mode=?, seat_numbers=?, time_slot=?, status='pending'
    WHERE id=? AND student_id=? AND status='new'
");
$totalStmt = $conn->prepare('SELECT IFNULL(SUM(quantity * price), 0) AS total FROM order_items WHERE order_id=?');
$totalStmt->bind_param('i', $order_id);
$totalStmt->execute();
$total = (float)($totalStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalStmt->close();
if ($total <= 0) json_exit(['status'=>'error','msg'=>'Cart is empty']);
$stmt->bind_param("sssii", $mode, $seat_numbers, $time_slot, $order_id, $student_db_id);
if (!$stmt->execute() || $stmt->affected_rows !== 1) {
    $stmt->close();
    json_exit(['status'=>'error','msg'=>'Order could not be submitted']);
}
$stmt->close();

sendOrderEmailNotification($conn, $student_db_id, $order_id, 'order_confirmed', 'Confirmed');
if ($mode === 'dine-in' && trim($seat_numbers) !== '') sendOrderEmailNotification($conn, $student_db_id, $order_id, 'reservation_confirmed', 'Seat reservation confirmed');

json_exit([
    'status'=>'success',
    'msg'=>'Order placed successfully',
    'order_id'=>$order_id
]);
?>

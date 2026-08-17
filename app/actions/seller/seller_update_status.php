<?php
// Always return JSON
header('Content-Type: application/json');

// Disable any PHP warnings or notices output to break JSON
error_reporting(0);

// Start session
session_start();
include 'db_connection.php';
require_once __DIR__ . '/../../services/EmailNotificationService.php';

// Helper function to return JSON and exit
function json_exit($arr){
    echo json_encode($arr);
    exit;
}

// ---------------------------
// Ensure seller is logged in
// ---------------------------
if (!isset($_SESSION['seller_id'])) {
    json_exit(['status'=>'error','msg'=>'Not logged in as seller']);
}

$seller_id = $_SESSION['seller_id'];

// ---------------------------
// Validate POST input
// ---------------------------
if (!isset($_POST['order_id'], $_POST['status'])) {
    json_exit(['status'=>'error','msg'=>'Missing order ID or status']);
}

$order_id = (int)$_POST['order_id'];
$status = strtolower(trim($_POST['status']));

// ---------------------------
// Only allow completed or cancelled
// ---------------------------
if (!in_array($status, ['completed', 'cancelled'])) {
    json_exit(['status'=>'error','msg'=>'Invalid status value']);
}

$orderMeta = null;
$stmt = $conn->prepare("SELECT seat_numbers, student_id FROM orders WHERE id=? AND seller_id=? LIMIT 1");
$stmt->bind_param("ii", $order_id, $seller_id);
$stmt->execute();
$orderMeta = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------------------------
// Update the order status
// ---------------------------
$stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=? AND seller_id=? AND LOWER(status)<>?");
$stmt->bind_param("siis", $status, $order_id, $seller_id, $status);

if ($stmt->execute()) {
    $statusChanged = $stmt->affected_rows === 1;
    if ($orderMeta && $statusChanged) {
        $emailType = $status === 'completed' ? 'order_completed' : 'order_cancelled';
        sendOrderEmailNotification($conn, (int)$orderMeta['student_id'], $order_id, $emailType, $status === 'completed' ? 'Ready for Pickup' : 'Cancelled');
        if ($status === 'cancelled' && !empty(trim($orderMeta['seat_numbers']))) sendOrderEmailNotification($conn, (int)$orderMeta['student_id'], $order_id, 'reservation_cancelled', 'Seat reservation cancelled');
    }
    if ($orderMeta && $statusChanged && !empty(trim($orderMeta['seat_numbers']))) {
        $seat_numbers = trim($orderMeta['seat_numbers']);
        $seats = array_filter(array_map('trim', explode(',', $seat_numbers)));
        $release = $conn->prepare("UPDATE seats SET is_reserved=0 WHERE seller_id=? AND seat_number=?");
        foreach ($seats as $seat) {
            $release->bind_param("is", $seller_id, $seat);
            $release->execute();
        }
        $release->close();
    }
    json_exit(['status'=>'success','msg'=>"Order status updated to ".ucfirst($status)]);
} else {
    json_exit(['status'=>'error','msg'=>'Failed to update order']);
}

$stmt->close();
$conn->close();
?>

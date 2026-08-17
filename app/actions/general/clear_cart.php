<?php
session_start();
include 'db_connection.php';
header('Content-Type: application/json; charset=utf-8');

// Must be logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in']);
    exit;
}

$student_id = $_SESSION['student_id'];

// Resolve actual DB student ID (id or student_id)
$stmt = $conn->prepare("SELECT id FROM students WHERE id=? OR student_id=? LIMIT 1");
$stmt->bind_param("is", $student_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Student not found']);
    exit;
}
$student_db_id = (int)$res->fetch_assoc()['id'];
$stmt->close();

// Fetch all pending orders for the student
$stmt = $conn->prepare("SELECT id FROM orders WHERE student_id=? AND status='pending'");
$stmt->bind_param("i", $student_db_id);
$stmt->execute();
$pendingOrders = $stmt->get_result();
$stmt->close();

// If no pending orders, simply return empty cart state
if ($pendingOrders->num_rows === 0) {
    echo json_encode(['status' => 'success', 'msg' => 'Cart cleared']);
    exit;
}

// Loop through each pending order and delete their items
while ($row = $pendingOrders->fetch_assoc()) {
    $order_id = (int)$row['id'];

    // Delete order items
    $delItems = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
    $delItems->bind_param("i", $order_id);
    $delItems->execute();
    $delItems->close();

    // Delete the order itself
    $delOrder = $conn->prepare("DELETE FROM orders WHERE id=?");
    $delOrder->bind_param("i", $order_id);
    $delOrder->execute();
    $delOrder->close();
}

echo json_encode(['status' => 'success', 'msg' => 'Cart cleared']);
exit;
?>
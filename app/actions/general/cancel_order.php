<?php
session_start();
include('db_connection.php');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    echo 'invalid';
    exit;
}

$order_id = $_POST['order_id'];

// Only students can cancel their own orders
if (!isset($_SESSION['student_id'])) {
    echo 'unauthorized';
    exit;
}

$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare("SELECT seller_id, seat_numbers FROM orders WHERE order_id = ? AND student_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $student_id);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND student_id = ?");
$stmt->bind_param("ii", $order_id, $student_id);

if ($stmt->execute()) {
    if ($meta && !empty(trim($meta['seat_numbers'])) && !empty($meta['seller_id'])) {
        $seat_numbers = trim($meta['seat_numbers']);
        $seats = array_filter(array_map('trim', explode(',', $seat_numbers)));
        $release = $conn->prepare("UPDATE seats SET is_reserved=0 WHERE seller_id=? AND seat_number=?");
        foreach ($seats as $seat) {
            $release->bind_param("is", $meta['seller_id'], $seat);
            $release->execute();
        }
        $release->close();
    }
    echo 'success';
} else {
    echo 'error';
}

$stmt->close();
$conn->close();
?>
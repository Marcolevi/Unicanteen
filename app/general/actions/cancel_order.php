<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';

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

$student_identifier = $_SESSION['student_id'];

$student = $conn->prepare("SELECT id FROM students WHERE id=? OR student_id=? LIMIT 1");
$student->bind_param("is", $student_identifier, $student_identifier);
$student->execute();
$studentRow = $student->get_result()->fetch_assoc();
$student->close();
if (!$studentRow) {
    echo 'unauthorized';
    exit;
}
$student_id = (int) $studentRow['id'];

$stmt = $conn->prepare("SELECT seller_id, seat_numbers FROM orders WHERE id = ? AND student_id = ? AND LOWER(status) IN ('pending','preparing') LIMIT 1");
$stmt->bind_param("ii", $order_id, $student_id);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_by = 'student' WHERE id = ? AND student_id = ? AND LOWER(status) IN ('pending','preparing')");
$stmt->bind_param("ii", $order_id, $student_id);

if ($stmt->execute() && $stmt->affected_rows === 1) {
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

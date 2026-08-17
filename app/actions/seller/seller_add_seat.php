<?php
session_start();
include 'db_connection.php';
header('Content-Type: application/json');

// -----------------------------
// CHECK SELLER LOGIN
// -----------------------------
if (!isset($_SESSION['seller_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in as seller']);
    exit;
}

$seller_id = intval($_SESSION['seller_id']);

// -----------------------------
// CHECK POST DATA
// -----------------------------
if (!isset($_POST['seat_number'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Missing seat number']);
    exit;
}

$seat_number = $_POST['seat_number'];

// -----------------------------
// INSERT SEAT WITH SELLER ID
// -----------------------------
$stmt = $conn->prepare("INSERT INTO seats (seller_id, seat_number, is_reserved) VALUES (?, ?, 0)");
$stmt->bind_param("is", $seller_id, $seat_number);

if ($stmt->execute()) {
    // Return success AND the inserted seat ID
    echo json_encode([
        'status' => 'success',
        'msg' => 'Seat added',
        'seat' => [
            'id' => $stmt->insert_id,
            'seat_number' => $seat_number,
            'status' => 0
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Failed to add seat']);
}

$stmt->close();
$conn->close();

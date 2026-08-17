<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Seller not logged in',
        'seats' => []
    ]);
    exit;
}

$seller_id = intval($_SESSION['seller_id']);

$stmt = $conn->prepare("SELECT id, seat_number, is_reserved FROM seats WHERE seller_id=? ORDER BY id ASC");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

$seats = [];
while ($row = $result->fetch_assoc()) {
    $seats[] = $row;
}

echo json_encode([
    'status' => 'success',
    'msg' => 'Seats fetched successfully',
    'seats' => $seats
]);
$conn->close();
exit;
?>

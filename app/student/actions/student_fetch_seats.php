<?php 
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ------------------------------
// Check if student is logged in
// ------------------------------
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in', 'seats' => []]);
    exit;
}

// ------------------------------
// Get canteen_id from GET or POST
// ------------------------------
$canteen_id = isset($_GET['canteen_id']) ? intval($_GET['canteen_id']) : 0;
if ($canteen_id <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Canteen ID missing', 'seats' => []]);
    exit;
}

// ------------------------------
// Fetch seller_id for this canteen
// ------------------------------
$stmt = $conn->prepare("SELECT seller_id FROM canteens WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $canteen_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Canteen not found', 'seats' => []]);
    exit;
}
$seller_id = (int)$res->fetch_assoc()['seller_id'];
$stmt->close();

// ------------------------------
// Fetch seats for this seller (NUMERIC ORDERING)
// ------------------------------
// Use CAST(... AS UNSIGNED) to ensure "10" comes after "2" etc.
$stmt = $conn->prepare(
    "SELECT id, seat_number, is_reserved 
     FROM seats 
     WHERE seller_id = ? 
     ORDER BY CAST(seat_number AS UNSIGNED) ASC"
);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

$seats = [];
while ($row = $result->fetch_assoc()) {
    $seats[] = [
        'id' => (int)$row['id'],
        'seat_number' => $row['seat_number'],
        'is_reserved' => (bool)$row['is_reserved'],
        'available' => !(bool)$row['is_reserved']
    ];
}
$stmt->close();
$conn->close();

// ------------------------------
// Return JSON
// ------------------------------
if (!empty($seats)) {
    echo json_encode(['status' => 'success', 'seats' => $seats]);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'No seats configured', 'seats' => []]);
}
?>

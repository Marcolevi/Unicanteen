<?php
session_start();
include 'db_connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['seller_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in as seller']);
    exit;
}

$seller_id = intval($_SESSION['seller_id']);
$seat_id = isset($_POST['seat_id']) ? intval($_POST['seat_id']) : null;

// 1️⃣ Update seat name
if ($seat_id && isset($_POST['seat_number'])) {
    $stmt = $conn->prepare("UPDATE seats SET seat_number = ? WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("sii", $_POST['seat_number'], $seat_id, $seller_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status'=>'success','msg'=>'Seat name updated']);
    exit;
}

// 2️⃣ Toggle reserved manually
if ($seat_id && isset($_POST['toggle']) && $_POST['toggle']==1) {
    $res = $conn->prepare("SELECT is_reserved FROM seats WHERE id=? AND seller_id=?");
    $res->bind_param("ii", $seat_id, $seller_id);
    $res->execute();
    $res->bind_result($current);
    $res->fetch();
    $res->close();

    $new_status = $current ? 0 : 1;

    $stmt = $conn->prepare("UPDATE seats SET is_reserved=? WHERE id=? AND seller_id=?");
    $stmt->bind_param("iii", $new_status, $seat_id, $seller_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status'=>'success','msg'=>'Seat toggled']);
    exit;
}

// 3️⃣ Mark available manually
if ($seat_id && isset($_POST['mark_available']) && $_POST['mark_available']==1) {
    $stmt = $conn->prepare("UPDATE seats SET is_reserved=0 WHERE id=? AND seller_id=?");
    $stmt->bind_param("ii", $seat_id, $seller_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status'=>'success','msg'=>'Seat marked available']);
    exit;
}

// 4️⃣ Automatically mark a seat reserved by seat_number (from student order)
if (isset($_POST['seat_number']) && isset($_POST['reserve']) && $_POST['reserve']==1) {
    $seat_number = $_POST['seat_number'];
    $stmt = $conn->prepare("UPDATE seats SET is_reserved=1 WHERE seat_number=? AND seller_id=?");
    $stmt->bind_param("si", $seat_number, $seller_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status'=>'success','msg'=>'Seat reserved automatically']);
    exit;
}

echo json_encode(['status'=>'error','msg'=>'No valid action provided']);
$conn->close();
?>
<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['seller_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in as seller']);
    exit;
}

$seller_id = intval($_SESSION['seller_id']);
$seat_id = isset($_POST['seat_id']) ? intval($_POST['seat_id']) : null;

// 1️⃣ Update seat name
if ($seat_id && isset($_POST['seat_number'])) {
    $seat_number = trim($_POST['seat_number']);
    if ($seat_number === '') {
        echo json_encode(['status'=>'error','msg'=>'Seat number is required']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE seats SET seat_number = ? WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("sii", $seat_number, $seat_id, $seller_id);
    $ok = $stmt->execute();
    $duplicate = $stmt->errno === 1062;
    $stmt->close();
    echo json_encode($ok ? ['status'=>'success','msg'=>'Seat name updated'] : ['status'=>'error','msg'=>$duplicate ? 'That seat number already exists' : 'Failed to update seat']);
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
    $stmt = $conn->prepare("UPDATE seats SET is_reserved=1 WHERE seat_number=? AND seller_id=? AND is_reserved=0");
    $stmt->bind_param("si", $seat_number, $seller_id);
    $stmt->execute();
    $reserved = $stmt->affected_rows === 1;
    $stmt->close();

    echo json_encode($reserved ? ['status'=>'success','msg'=>'Seat reserved automatically'] : ['status'=>'error','msg'=>'Seat is no longer available']);
    exit;
}

echo json_encode(['status'=>'error','msg'=>'No valid action provided']);
$conn->close();
?>

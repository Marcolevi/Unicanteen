<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

// Ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

// Resolve numeric student id (works with id or student_id)
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT id FROM students WHERE id=? OR student_id=? LIMIT 1");
$stmt->bind_param("is", $student_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status'=>'error','msg'=>'Student not found']);
    exit;
}
$student_db_id = (int)$res->fetch_assoc()['id'];
$stmt->close();

// Fetch draft carts only. Pending orders have already been submitted.
$stmt = $conn->prepare("
    SELECT o.id AS order_id, o.canteen_id, c.canteen_name,
           oi.food_id, f.name AS food_name, oi.price, oi.quantity
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN foods f ON oi.food_id = f.id
    JOIN canteens c ON o.canteen_id = c.id
    WHERE o.student_id = ? AND o.status = 'new'
    ORDER BY o.id ASC, oi.id ASC
");
$stmt->bind_param("i", $student_db_id);
$stmt->execute();
$result = $stmt->get_result();

$byCanteen = [];
while ($row = $result->fetch_assoc()) {
    $cid = (int)$row['canteen_id'];
    if (!isset($byCanteen[$cid])) {
        $byCanteen[$cid] = [
            'canteen_id' => $cid,
            'canteen_name' => $row['canteen_name'],
            'items' => []
        ];
    }
    $byCanteen[$cid]['items'][] = [
        'food_id' => (int)$row['food_id'],
        'name'    => $row['food_name'],
        'price'   => (float)$row['price'],
        'quantity'=> (int)$row['quantity'],
        'subtotal'=> (float)$row['price'] * (int)$row['quantity']
    ];
}
$stmt->close();

// Optionally include recent submitted orders (last 5 non-draft)
$recent = [];
$st = $conn->prepare("
    SELECT o.id AS order_id, o.total, o.status, c.canteen_name,
           GROUP_CONCAT(CONCAT(f.name, ' x', oi.quantity) SEPARATOR ', ') AS foods
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN foods f ON oi.food_id = f.id
    JOIN canteens c ON o.canteen_id = c.id
    WHERE o.student_id = ? AND o.status <> 'new'
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$st->bind_param("i", $student_db_id);
$st->execute();
$rres = $st->get_result();
while ($row = $rres->fetch_assoc()) {
    $recent[] = [
        'order_id' => (int)$row['order_id'],
        'total'    => (float)$row['total'],
        'status'   => $row['status'],
        'canteen_name' => $row['canteen_name'],
        'foods'    => $row['foods']
    ];
}
$st->close();

// Return response
echo json_encode([
    'status' => 'success',
    'cart'   => array_values($byCanteen),
    'recent_orders' => $recent
]);
exit;
?>

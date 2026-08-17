<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';

header('Content-Type: application/json; charset=UTF-8');

// Helper
function json_exit($arr) {
    echo json_encode($arr);
    exit;
}

// Ensure logged in
if (!isset($_SESSION['student_id'])) {
    json_exit(['status'=>'error','msg'=>'Not logged in']);
}

$student_id = $_SESSION['student_id'];

// Resolve numeric ID from session
$stmt = $conn->prepare("SELECT id FROM students WHERE id=? OR student_id=? LIMIT 1");
$stmt->bind_param("is", $student_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) json_exit(['status'=>'error','msg'=>'Student not found']);
$student_db_id = (int)$res->fetch_assoc()['id'];
$stmt->close();

// Required: food_id
$food_id = isset($_POST['food_id']) ? intval($_POST['food_id']) : 0;
if (!$food_id) json_exit(['status'=>'error','msg'=>'Food ID missing']);

// Fetch food details
$stmt = $conn->prepare("SELECT id, canteen_id, price, stock_quantity, is_available FROM foods WHERE id=? LIMIT 1");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) json_exit(['status'=>'error','msg'=>'Food not found']);
$food = $res->fetch_assoc();
$stmt->close();

if (!(int)$food['is_available'] || (int)$food['stock_quantity'] < 1) {
    json_exit(['status'=>'error','msg'=>'This item is out of stock']);
}

$canteen_id = (int)$food['canteen_id'];
$price      = (float)$food['price'];

// A cart is a draft. Only submitted orders use the pending status.
$stmt = $conn->prepare("
    SELECT id FROM orders 
    WHERE student_id=? AND canteen_id=? AND status='new'
    LIMIT 1
");
$stmt->bind_param("ii", $student_db_id, $canteen_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $order_id = (int)$res->fetch_assoc()['id'];
    $stmt->close();
} else {
    $stmt->close();

    // Get seller_id from canteen
    $stmt = $conn->prepare("SELECT seller_id FROM canteens WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $canteen_id);
    $stmt->execute();
    $seller = $stmt->get_result()->fetch_assoc();
    $seller_id = (int)$seller['seller_id'];
    $stmt->close();

    // Create new order
    $stmt = $conn->prepare("
        INSERT INTO orders (student_id, seller_id, canteen_id, total, status, created_at)
        VALUES (?, ?, ?, 0, 'new', NOW())
    ");
    $stmt->bind_param("iii", $student_db_id, $seller_id, $canteen_id);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();
}

// Check if item exists → increment qty
$stmt = $conn->prepare("SELECT id, quantity FROM order_items WHERE order_id=? AND food_id=? LIMIT 1");
$stmt->bind_param("ii", $order_id, $food_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $item = $res->fetch_assoc();
    $new_qty = $item['quantity'] + 1;
    $stmt->close();

    if ($new_qty > (int)$food['stock_quantity']) {
        json_exit(['status'=>'error','msg'=>'Not enough stock available']);
    }

    $stmt = $conn->prepare("UPDATE order_items SET quantity=? WHERE id=?");
    $stmt->bind_param("ii", $new_qty, $item['id']);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, food_id, quantity, price, seller_id)
        VALUES (?, ?, 1, ?, ?)
    ");
    $stmt->bind_param("iidi", $order_id, $food_id, $price, $seller_id);
    $stmt->execute();
    $stmt->close();
}

// Update total
$stmt = $conn->prepare("
    SELECT SUM(quantity * price) AS total 
    FROM order_items 
    WHERE order_id=?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("UPDATE orders SET total=? WHERE id=?");
$stmt->bind_param("di", $total, $order_id);
$stmt->execute();
$stmt->close();

json_exit([
    'status'=>'success',
    'msg'=>'Item added to cart',
    'order_id'=>$order_id,
    'total'=>$total
]);
?>

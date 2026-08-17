<?php
require_once __DIR__ . '/../../services/EmailNotificationService.php';
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Helper to return JSON and exit
function json_exit($arr) {
    echo json_encode($arr);
    exit;
}

// Ensure logged in
if (!isset($_SESSION['student_id'])) {
    json_exit(['status' => 'error', 'msg' => 'Not logged in']);
}
$student_id = $_SESSION['student_id'];

// Resolve DB student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE id=? OR student_id=? LIMIT 1");
$stmt->bind_param("is", $student_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) json_exit(['status'=>'error','msg'=>'Student not found']);
$student_db_id = (int)$res->fetch_assoc()['id'];
$stmt->close();

// Read POST inputs
$action     = $_POST['action'] ?? '';
$food_id    = isset($_POST['food_id']) ? intval($_POST['food_id']) : 0;
$canteen_id = isset($_POST['canteen_id']) ? intval($_POST['canteen_id']) : 0;
$order_type = $_POST['order_type'] ?? '';
$seat       = $_POST['seat'] ?? '';
$quantity   = isset($_POST['quantity']) ? max(1,intval($_POST['quantity'])) : 1;

// ---------------------
// Helper: Get or create pending/new order
// ---------------------
function get_or_create_order($conn, $student_db_id, $canteen_id, $food_id=0, $mode='', $seat='') {
    $seller_id = 0;
    $price = 0;
    $use_canteen = $canteen_id;

    if ($food_id) {
        $stmt = $conn->prepare("
            SELECT f.canteen_id AS f_canteen_id, c.seller_id AS seller_id, f.price AS price,
                   f.stock_quantity, f.is_available
            FROM foods f
            JOIN canteens c ON c.id = f.canteen_id
            WHERE f.id=? LIMIT 1
        ");
        if (!$stmt) return false;
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return false; }
        $row = $res->fetch_assoc();
        $use_canteen = (int)$row['f_canteen_id'];
        $seller_id = (int)$row['seller_id'];
        $price = (float)$row['price'];
        $stmt->close();
    }

    if (!$seller_id && $use_canteen) {
        $st = $conn->prepare("SELECT seller_id FROM canteens WHERE id=? LIMIT 1");
        $st->bind_param("i", $use_canteen);
        $st->execute();
        $r = $st->get_result();
        if ($r && $r->num_rows>0) $seller_id = (int)$r->fetch_assoc()['seller_id'];
        $st->close();
    }

    // -----------------------
    // Check for existing order
    // -----------------------
    if ($mode === 'dine-in' && $seat) {
        // For dine-in, match seat number
        $stmt2 = $conn->prepare("
            SELECT id 
            FROM orders 
            WHERE student_id=? 
              AND canteen_id=? 
              AND mode='dine-in' 
              AND seat_numbers=? 
              AND status = 'new' 
            LIMIT 1
        ");
        $stmt2->bind_param("iis", $student_db_id, $use_canteen, $seat);
    } else {
        // Takeout or dine-in without seat: match mode only
        $mode_val = $mode ?: 'takeout';
        $stmt2 = $conn->prepare("
            SELECT id 
            FROM orders 
            WHERE student_id=? 
              AND canteen_id=? 
              AND mode=? 
              AND status = 'new' 
            LIMIT 1
        ");
        $stmt2->bind_param("iis", $student_db_id, $use_canteen, $mode_val);
    }

    $stmt2->execute();
    $res2 = $stmt2->get_result();

    if ($res2->num_rows > 0) {
        $order_id = (int)$res2->fetch_assoc()['id'];
        $stmt2->close();
        return ['order_id'=>$order_id, 'canteen_id'=>$use_canteen, 'seller_id'=>$seller_id, 'price'=>$price,
                'stock_quantity'=>(int)$row['stock_quantity'], 'is_available'=>(int)$row['is_available']];
    }
    $stmt2->close();

    // -----------------------
    // Create new order
    // -----------------------
    $seat_param = ($mode === 'dine-in' && $seat) ? $seat : '';
    $mode_val = $mode ?: 'takeout';

    $ins = $conn->prepare("
        INSERT INTO orders 
            (student_id, seller_id, canteen_id, mode, seat_numbers, total, status, created_at)
        VALUES 
            (?, ?, ?, ?, ?, 0, 'new', NOW())
    ");
    $ins->bind_param("iiiss", $student_db_id, $seller_id, $use_canteen, $mode_val, $seat_param);
    $ins->execute();
    $new_order_id = (int)$ins->insert_id;
    $ins->close();

    return ['order_id'=>$new_order_id, 'canteen_id'=>$use_canteen, 'seller_id'=>$seller_id, 'price'=>$price,
            'stock_quantity'=>PHP_INT_MAX, 'is_available'=>1];
}


// ---------------------
// Helper: Fetch cart
// ---------------------
function fetch_cart($conn, $student_db_id) {
    $stmt = $conn->prepare("
        SELECT o.id AS order_id, o.canteen_id, o.mode, c.canteen_name, f.id AS food_id, f.name, f.price, oi.quantity
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN foods f ON oi.food_id = f.id
        JOIN canteens c ON o.canteen_id = c.id
        WHERE o.student_id=? AND o.status = 'new'
        ORDER BY o.id ASC
    ");
    $stmt->bind_param("i", $student_db_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $temp = [];
    while ($row = $res->fetch_assoc()) {
        $key = $row['canteen_id'] . '-' . ($row['mode'] ?: 'takeout'); // separate by mode
        if (!isset($temp[$key])) {
            $temp[$key] = [
                'canteen_id' => $row['canteen_id'],
                'canteen_name' => $row['canteen_name'],
                'mode' => $row['mode'] ?: 'takeout',
                'items' => []
            ];
        }
        $temp[$key]['items'][] = [
            'food_id' => (int)$row['food_id'],
            'name' => $row['name'],
            'price' => floatval($row['price']),
            'quantity' => intval($row['quantity']),
            'subtotal' => floatval($row['price'])*intval($row['quantity'])
        ];
    }
    $stmt->close();
    return array_values($temp);
}

// ---------------------
// ACTIONS
// ---------------------
switch($action) {
    case 'add':
    case 'decrease':
    case 'remove':
    case 'set':
        if (!$food_id) json_exit(['status'=>'error','msg'=>'food_id required']);
        $order_info = get_or_create_order($conn, $student_db_id, $canteen_id, $food_id, $order_type);
        if (!$order_info) json_exit(['status'=>'error','msg'=>'Could not find/create order']);
        $order_id = $order_info['order_id'];

        $stmt = $conn->prepare("SELECT id, quantity FROM order_items WHERE order_id=? AND food_id=? LIMIT 1");
        $stmt->bind_param("ii", $order_id, $food_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $item = $res->fetch_assoc();
            $item_id = (int)$item['id'];
            $qty = (int)$item['quantity'];
            $stmt->close();

            if ($action==='add') $qty++;
            elseif ($action==='decrease') $qty--;
            elseif ($action==='set') $qty = $quantity;
            elseif ($action==='remove') $qty=0;

            if ($qty > 0 && (!$order_info['is_available'] || $qty > $order_info['stock_quantity'])) {
                json_exit(['status'=>'error','msg'=>'This item does not have enough stock available']);
            }

            if ($qty>0) {
                $stmt = $conn->prepare("UPDATE order_items SET quantity=? WHERE id=?");
                $stmt->bind_param("ii",$qty,$item_id);
                $stmt->execute(); $stmt->close();
            } else {
                $stmt = $conn->prepare("DELETE FROM order_items WHERE id=?");
                $stmt->bind_param("i",$item_id);
                $stmt->execute(); $stmt->close();
            }
        } else {
            $stmt->close();
            if ($action==='add' || $action==='set') {
                if (!$order_info['is_available'] || $quantity > $order_info['stock_quantity']) {
                    json_exit(['status'=>'error','msg'=>'This item does not have enough stock available']);
                }
                $price = $order_info['price'];
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price, seller_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidi", $order_id, $food_id, $quantity, $price, $order_info['seller_id']);
                $stmt->execute(); $stmt->close();
            } else {
                json_exit(['status'=>'error','msg'=>'Item not in cart']);
            }
        }

        // Update order total
        $stmt = $conn->prepare("SELECT IFNULL(SUM(quantity*price),0) AS total FROM order_items WHERE order_id=?");
        $stmt->bind_param("i",$order_id);
        $stmt->execute();
        $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare("UPDATE orders SET total=? WHERE id=?");
        $stmt->bind_param("di",$total,$order_id);
        $stmt->execute(); $stmt->close();

        json_exit(['status'=>'success','cart'=>fetch_cart($conn,$student_db_id),'order_id'=>$order_id,'total'=>$total]);
        break;

    case 'set_order_type':
        if (!$canteen_id) json_exit(['status'=>'error','msg'=>'Canteen ID required']);
        if (!$order_type) json_exit(['status'=>'error','msg'=>'Order type missing']);
        $order_info = get_or_create_order($conn, $student_db_id, $canteen_id, 0, $order_type);
        $order_id = $order_info['order_id'];

        $seat_param = trim($seat);
        $upd = $conn->prepare("UPDATE orders SET mode=?, seat_numbers=? WHERE id=?");
        $upd->bind_param("ssi",$order_type,$seat_param,$order_id);
        $upd->execute(); $upd->close();

        json_exit(['status'=>'success','msg'=>'Order type saved','cart'=>fetch_cart($conn,$student_db_id)]);
        break;

    case 'clear_canteen_cart':
        if (!$canteen_id) json_exit(['status'=>'error','msg'=>'Canteen ID required']);

        $stmt = $conn->prepare("SELECT id FROM orders WHERE student_id=? AND canteen_id=? AND status='new' LIMIT 1");
        $stmt->bind_param("ii",$student_db_id,$canteen_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if($res->num_rows===0) {
            $stmt->close();
            json_exit(['status'=>'success','msg'=>'Cart already empty']);
        }
        $order_id = (int)$res->fetch_assoc()['id'];
        $stmt->close();

        $del = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
        $del->bind_param("i",$order_id);
        $del->execute();
        $del->close();

        $upd = $conn->prepare("UPDATE orders SET total=0 WHERE id=?");
        $upd->bind_param("i",$order_id);
        $upd->execute();
        $upd->close();

        json_exit(['status'=>'success','msg'=>'Cart cleared for this canteen','cart'=>fetch_cart($conn,$student_db_id)]);
        break;

    case 'place_order':
        if (!$canteen_id) json_exit(['status'=>'error','msg'=>'Canteen ID missing']);
        if (!$order_type) $order_type = 'takeout';
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        if (!in_array($payment_method, ['cash', 'gcash_sandbox'], true)) {
            json_exit(['status'=>'error','msg'=>'Invalid payment method']);
        }
        if ($payment_method === 'gcash_sandbox' && $payment_reference === '') {
            json_exit(['status'=>'error','msg'=>'Complete the GCash sandbox payment first']);
        }
        $payment_status = $payment_method === 'gcash_sandbox' ? 'paid_sandbox' : 'unpaid';
        $seat_param = trim($seat ?? '');

        $cart_json = $_POST['cart'] ?? '';
        $cart_items = json_decode($cart_json, true);
        if (!is_array($cart_items) || count($cart_items) === 0) {
            json_exit(['status'=>'error','msg'=>'Cart is empty']);
        }

        $conn->begin_transaction();

        // A confirmed checkout must always create a fresh order. Reusing a
        // previous pending order caused later checkouts to merge into it.
        $sellerStmt = $conn->prepare("SELECT seller_id FROM canteens WHERE id=? LIMIT 1");
        $sellerStmt->bind_param("i", $canteen_id);
        $sellerStmt->execute();
        $seller = $sellerStmt->get_result()->fetch_assoc();
        $sellerStmt->close();
        if (!$seller) {
            $conn->rollback();
            json_exit(['status'=>'error','msg'=>'Canteen not found']);
        }
        $seller_id = (int)$seller['seller_id'];

        // The cart is represented by a draft `new` order. Remove only that
        // draft for this canteen before creating the submitted order.
        $draftItems = $conn->prepare("DELETE oi FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.student_id=? AND o.canteen_id=? AND o.status='new'");
        $draftItems->bind_param("ii", $student_db_id, $canteen_id);
        if (!$draftItems->execute()) {
            $draftItems->close();
            $conn->rollback();
            json_exit(['status'=>'error','msg'=>'Could not finalize the cart']);
        }
        $draftItems->close();
        $draftOrders = $conn->prepare("DELETE FROM orders WHERE student_id=? AND canteen_id=? AND status='new'");
        $draftOrders->bind_param("ii", $student_db_id, $canteen_id);
        if (!$draftOrders->execute()) {
            $draftOrders->close();
            $conn->rollback();
            json_exit(['status'=>'error','msg'=>'Could not finalize the cart']);
        }
        $draftOrders->close();

        $newOrder = $conn->prepare("INSERT INTO orders (student_id, seller_id, canteen_id, mode, seat_numbers, total, status, created_at) VALUES (?, ?, ?, ?, ?, 0, 'pending', NOW())");
        $newOrder->bind_param("iiiss", $student_db_id, $seller_id, $canteen_id, $order_type, $seat_param);
        if (!$newOrder->execute()) {
            $newOrder->close();
            $conn->rollback();
            json_exit(['status'=>'error','msg'=>'Could not create the order']);
        }
        $order_id = (int)$newOrder->insert_id;
        $newOrder->close();
        $order_info = ['seller_id' => $seller_id];

        // Stock is reduced only once the order is submitted, not while it is in a cart.
        // Conditional updates make this safe even when two students check out simultaneously.
        $validatedItems = [];
        foreach ($cart_items as $item) {
            $item_food_id = (int)($item['food_id'] ?? 0);
            $item_quantity = max(1, (int)($item['quantity'] ?? 0));
            if ($item_food_id <= 0) {
                $conn->rollback();
                json_exit(['status'=>'error','msg'=>'Invalid food item']);
            }
            $stock = $conn->prepare("UPDATE foods SET stock_quantity = stock_quantity - ? WHERE id=? AND canteen_id=? AND is_available=1 AND stock_quantity >= ?");
            $stock->bind_param("iiii", $item_quantity, $item_food_id, $canteen_id, $item_quantity);
            $stock->execute();
            $updated = $stock->affected_rows;
            $stock->close();
            if ($updated !== 1) {
                $conn->rollback();
                json_exit(['status'=>'error','msg'=>'One or more items are unavailable or no longer have enough stock']);
            }

            $priceStmt = $conn->prepare("SELECT price FROM foods WHERE id=? AND canteen_id=? LIMIT 1");
            $priceStmt->bind_param("ii", $item_food_id, $canteen_id);
            $priceStmt->execute();
            $food = $priceStmt->get_result()->fetch_assoc();
            $priceStmt->close();
            if (!$food) {
                $conn->rollback();
                json_exit(['status'=>'error','msg'=>'Food item is no longer available']);
            }
            $validatedItems[] = ['food_id' => $item_food_id, 'quantity' => $item_quantity, 'price' => (float) $food['price']];
        }

        // Insert server-validated items and prices. Client prices are never trusted.
        foreach($validatedItems as $item){
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price, seller_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "iiidi",
                $order_id,
                $item['food_id'],
                $item['quantity'],
                $item['price'],
                $order_info['seller_id']
            );
            $stmt->execute();
            $stmt->close();
        }

        // Update total
        $stmt = $conn->prepare("SELECT IFNULL(SUM(quantity*price),0) AS total FROM order_items WHERE order_id=?");
        $stmt->bind_param("i",$order_id);
        $stmt->execute();
        $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        if ($total <= 0) {
            $conn->rollback();
            json_exit(['status'=>'error','msg'=>'Cart is empty']);
        }

        // Update order info (total, mode, seat)
        $upd = $conn->prepare("UPDATE orders SET total=?, mode=?, seat_numbers=?, payment_method=?, payment_status=?, payment_reference=? WHERE id=?");
        $upd->bind_param("dsssssi",$total,$order_type,$seat_param,$payment_method,$payment_status,$payment_reference,$order_id);
        $upd->execute();
        $upd->close();

        // Reserve seat if dine-in
        if ($order_type === 'dine-in' && $seat_param) {
            $stmt2 = $conn->prepare("UPDATE seats SET is_reserved=1 WHERE seat_number=? AND seller_id=? AND is_reserved=0");
            $stmt2->bind_param("si", $seat_param, $order_info['seller_id']);
            $stmt2->execute();
            $seatClaimed = $stmt2->affected_rows === 1;
            $stmt2->close();
            if (!$seatClaimed) {
                $conn->rollback();
                json_exit(['status'=>'error','msg'=>'That seat is no longer available']);
            }
        }

        $conn->commit();
        // Email delivery is supplementary. A mail configuration/runtime failure
        // must never turn a committed checkout into an invalid JSON response.
        try {
            sendOrderEmailNotification($conn,$student_db_id,$order_id,'order_confirmed','Confirmed');
            if($order_type==='dine-in'&&$seat_param) sendOrderEmailNotification($conn,$student_db_id,$order_id,'reservation_confirmed','Seat reservation confirmed');
        } catch (Throwable $exception) {
            error_log('Order email notification failed after checkout: ' . $exception->getMessage());
        }

        json_exit([
            'status'=>'success',
            'msg'=>'Order ready for seller',
            'order_id'=>$order_id,
            'total'=>$total
        ]);
        break;

    default:
        json_exit(['status'=>'error','msg'=>'Unknown action']);
}
?>

<?php
session_start();
include 'db_connection.php';

if(!isset($_SESSION['seller_id'])){
    echo "Not logged in";
    exit;
}

$seller_id = $_SESSION['seller_id'];

// Delete all orders for this seller
$conn->query("DELETE o, oi 
             FROM orders o 
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.seller_id = $seller_id");

echo "All orders cleared successfully.";
?>

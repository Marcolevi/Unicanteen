<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['seller_id'])) exit;

$seller_id = $_SESSION['seller_id'];

$canteen_q = $conn->query("SELECT id FROM canteens WHERE seller_id = $seller_id LIMIT 1");
$canteen = $canteen_q->fetch_assoc();
if (!$canteen) exit;

$canteen_id = $canteen['id'];
$foods = $conn->query("SELECT * FROM foods WHERE canteen_id = $canteen_id ORDER BY created_at DESC");

$foods_arr = [];
while($food = $foods->fetch_assoc()){
    $foods_arr[] = $food;
}

echo json_encode($foods_arr);

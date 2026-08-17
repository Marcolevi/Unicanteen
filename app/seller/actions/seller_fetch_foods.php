<?php
session_start();
require_once __DIR__ . '/../../../config/db_connection.php';
require_once __DIR__ . '/../../includes/food_images.php';

if (!isset($_SESSION['seller_id'])) exit;

$seller_id = $_SESSION['seller_id'];

$canteen_q = $conn->query("SELECT id FROM canteens WHERE seller_id = $seller_id LIMIT 1");
$canteen = $canteen_q->fetch_assoc();
if (!$canteen) exit;

$canteen_id = $canteen['id'];
$foods = $conn->query("SELECT * FROM foods WHERE canteen_id = $canteen_id ORDER BY created_at DESC");

$foods_arr = [];
while($food = $foods->fetch_assoc()){
    $food['image'] = foodImageDisplayUrl($food['image']);
    $foods_arr[] = $food;
}

echo json_encode($foods_arr);

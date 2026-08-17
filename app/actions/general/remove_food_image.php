<?php
session_start();
include 'db_connection.php';

if(!isset($_SESSION['seller_id'])) exit(json_encode(['errors'=>['Not logged in']]));

$seller_id=$_SESSION['seller_id'];
$food_id=intval($_GET['id'] ?? 0);
if(!$food_id) exit(json_encode(['errors'=>['Invalid food ID']]));

// Fetch food
$stmt=$conn->prepare("SELECT f.*, c.seller_id FROM foods f JOIN canteens c ON f.canteen_id=c.id WHERE f.id=? LIMIT 1");
$stmt->bind_param("i",$food_id);
$stmt->execute();
$food=$stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$food || $food['seller_id']!=$seller_id) exit(json_encode(['errors'=>['Unauthorized']]));

if(!empty($food['image']) && file_exists($food['image'])) unlink($food['image']);

$stmt=$conn->prepare("UPDATE foods SET image=NULL WHERE id=?");
$stmt->bind_param("i",$food_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success'=>true]);
?>

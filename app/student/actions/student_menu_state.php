<?php
session_start(); require __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['student_id'])) { http_response_code(401); echo '[]'; exit; }
$result=$conn->query("SELECT f.id,f.canteen_id,f.stock_quantity,f.is_available FROM foods f JOIN canteens c ON c.id=f.canteen_id JOIN sellers s ON s.id=c.seller_id WHERE c.is_active=1 AND s.approval_status='approved' ORDER BY f.id");
$state=[];while($row=$result->fetch_assoc())$state[]=['id'=>(int)$row['id'],'canteen_id'=>(int)$row['canteen_id'],'stock'=>(int)$row['stock_quantity'],'available'=>(int)$row['is_available']];echo json_encode($state);

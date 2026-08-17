<?php
session_start();
require __DIR__ . '/../../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['student_id'])) { http_response_code(401); echo json_encode([]); exit; }
$result=$conn->query("SELECT c.id,c.is_active FROM canteens c JOIN sellers s ON s.id=c.seller_id WHERE s.approval_status='approved' ORDER BY c.id");
$canteens=[];while($row=$result->fetch_assoc())$canteens[]=['id'=>(int)$row['id'],'is_active'=>(int)$row['is_active']];echo json_encode($canteens);

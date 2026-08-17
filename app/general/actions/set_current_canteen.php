<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in']);
    exit;
}

if (!isset($_POST['canteen_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'No canteen selected']);
    exit;
}

$_SESSION['current_seller_id'] = (int)$_POST['canteen_id'];
echo json_encode(['status' => 'success']);

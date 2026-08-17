<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit;
}

$seller_id = $_SESSION['seller_id'];

// Get food ID from query
$food_id = intval($_GET['id'] ?? 0);
if (!$food_id) {
    die("Invalid food ID.");
}

// Fetch the food item and ensure it belongs to this seller
$stmt = $conn->prepare("
    SELECT f.image, c.seller_id 
    FROM foods f 
    JOIN canteens c ON f.canteen_id = c.id 
    WHERE f.id = ? LIMIT 1
");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();
$stmt->close();

if (!$food || $food['seller_id'] != $seller_id) {
    die("You don't have permission to delete this food item.");
}

// Delete image file if it exists
if (!empty($food['image']) && file_exists($food['image'])) {
    unlink($food['image']);
}

// Delete food from database
$stmt = $conn->prepare("DELETE FROM foods WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$stmt->close();

// Redirect back to menu with success message
header("Location: seller_menu.php?msg=Food+item+deleted+successfully");
exit;
?>

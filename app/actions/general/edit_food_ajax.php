<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['seller_id'])) exit(json_encode(['errors'=>['Not logged in']]));

$seller_id = $_SESSION['seller_id'];
$food_id = intval($_GET['id'] ?? 0);
if(!$food_id) exit(json_encode(['errors'=>['Invalid food ID']]));

// Fetch food
$stmt = $conn->prepare("SELECT f.*, c.seller_id FROM foods f JOIN canteens c ON f.canteen_id=c.id WHERE f.id=? LIMIT 1");
$stmt->bind_param("i",$food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$food || $food['seller_id'] != $seller_id) exit(json_encode(['errors'=>['Unauthorized']]));

$errors=[];
$name = trim($_POST['name'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$image = $food['image'];

if($name==='') $errors[]='Food name cannot be empty.';
if($price<=0) $errors[]='Invalid price.';

// Handle image upload
if(!empty($_FILES['image']['name'])){
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if(!in_array($ext,$allowed)) $errors[]='Invalid file format.';
    else{
        $dir="uploads/foods/";
        if(!is_dir($dir)) mkdir($dir,0777,true);
        $newName=uniqid("food_").".".$ext;
        $path=$dir.$newName;
        if(move_uploaded_file($_FILES['image']['tmp_name'],$path)){
            if(!empty($food['image']) && file_exists($food['image'])) unlink($food['image']);
            $image=$path;
        }else $errors[]='Upload failed.';
    }
}

if($errors) exit(json_encode(['errors'=>$errors]));

$stmt = $conn->prepare("UPDATE foods SET name=?, price=?, image=? WHERE id=?");
$stmt->bind_param("sdsi",$name,$price,$image,$food_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success'=>true,
    'name'=>$name,
    'price'=>$price,
    'image'=>$image
]);
?>

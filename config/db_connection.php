<?php
$envPath = dirname(__DIR__) . '/.env';
ini_set('default_charset', 'UTF-8');
if (is_file($envPath)) foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { if ($line[0] !== '#' && str_contains($line, '=')) { [$key,$value] = explode('=', $line, 2); $_ENV[trim($key)] = trim($value); } }
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$db   = $_ENV['DB_DATABASE'] ?? 'unicanteen';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Ensure archive columns exist for order visibility separation.
$archiveColumns = [
    'student_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'seller_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
];
foreach ($archiveColumns as $column => $definition) {
    $escaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$escaped'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN $column $definition");
    }
}

// Backwards-compatible inventory migration for existing local databases.
$foodInventoryColumns = [
    'stock_quantity' => 'INT NOT NULL DEFAULT 0',
    'is_available' => 'TINYINT(1) NOT NULL DEFAULT 1',
];
foreach ($foodInventoryColumns as $column => $definition) {
    $escaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM foods LIKE '$escaped'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE foods ADD COLUMN $column $definition");
    }
}

$paymentColumns = [
    'payment_method' => "VARCHAR(30) NOT NULL DEFAULT 'cash'",
    'payment_status' => "VARCHAR(30) NOT NULL DEFAULT 'unpaid'",
    'payment_reference' => 'VARCHAR(100) DEFAULT NULL',
    'payment_confirmed_at' => 'DATETIME DEFAULT NULL',
];
foreach ($paymentColumns as $column => $definition) {
    $escaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$escaped'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN $column $definition");
    }
}

$sellerApproval = $conn->query("SHOW COLUMNS FROM sellers LIKE 'approval_status'");
if ($sellerApproval && $sellerApproval->num_rows === 0) {
    $conn->query("ALTER TABLE sellers ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    // Preserve access for sellers that existed before approvals were introduced.
    $conn->query("UPDATE sellers SET approval_status='approved' WHERE approval_status='pending'");
}
$conn->query("CREATE TABLE IF NOT EXISTS admins (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(150) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS announcements (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, message TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS admin_audit_log (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, action_text VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$canteenStatus=$conn->query("SHOW COLUMNS FROM canteens LIKE 'is_active'"); if($canteenStatus&&$canteenStatus->num_rows===0)$conn->query("ALTER TABLE canteens ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("CREATE TABLE IF NOT EXISTS pending_registrations (id INT AUTO_INCREMENT PRIMARY KEY, user_type VARCHAR(10) NOT NULL, user_identifier VARCHAR(50) DEFAULT NULL, full_name VARCHAR(150) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, contact VARCHAR(30) DEFAULT NULL, password_hash VARCHAR(255) NOT NULL, otp_hash VARCHAR(255) NOT NULL, otp_expires_at DATETIME NOT NULL, otp_attempts TINYINT NOT NULL DEFAULT 0, resend_available_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_otps (id INT AUTO_INCREMENT PRIMARY KEY, user_type VARCHAR(10) NOT NULL, user_id INT NOT NULL, email VARCHAR(150) NOT NULL, otp_hash VARCHAR(255) NOT NULL, otp_expires_at DATETIME NOT NULL, otp_attempts TINYINT NOT NULL DEFAULT 0, resend_available_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$emailSetting=$conn->query("SHOW COLUMNS FROM students LIKE 'email_notifications_enabled'");if($emailSetting&&$emailSetting->num_rows===0)$conn->query("ALTER TABLE students ADD COLUMN email_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1");
$conn->query("CREATE TABLE IF NOT EXISTS email_notifications (id INT AUTO_INCREMENT PRIMARY KEY,student_id INT NOT NULL,order_id INT NOT NULL,notification_type VARCHAR(50) NOT NULL,recipient_email VARCHAR(150) NOT NULL,subject VARCHAR(180) NOT NULL,message TEXT NOT NULL,status VARCHAR(20) NOT NULL,error_message VARCHAR(255) DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,sent_at DATETIME DEFAULT NULL,UNIQUE KEY email_notification_once(student_id,order_id,notification_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY,seller_id INT NOT NULL,student_id INT NOT NULL,order_id INT NOT NULL DEFAULT 0,message TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'unread',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_notifications_seller_created(seller_id,created_at),KEY idx_notifications_student_created(student_id,created_at),KEY idx_notifications_order(order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>

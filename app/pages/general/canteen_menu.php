<?php
require_once __DIR__ . '/../../components/common_footer.php';
session_start();
include 'db_connection.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$canteen_id = intval($_GET['id'] ?? 0);

if (!$canteen_id) {
    die("Invalid canteen.");
}

// Fetch canteen info
$canteen_stmt = $conn->prepare("SELECT c.canteen_name, c.description, c.image FROM canteens c JOIN sellers s ON s.id=c.seller_id WHERE c.id=? AND s.approval_status='approved' AND c.is_active=1 LIMIT 1");
$canteen_stmt->bind_param("i", $canteen_id);
$canteen_stmt->execute();
$canteen_result = $canteen_stmt->get_result();
$canteen = $canteen_result->fetch_assoc();
$canteen_stmt->close();

if (!$canteen) die("Canteen not found.");

// Fetch foods for this canteen
$foods_stmt = $conn->prepare("SELECT id, name, price, image, stock_quantity, is_available FROM foods WHERE canteen_id=?");
$foods_stmt->bind_param("i", $canteen_id);
$foods_stmt->execute();
$foods_result = $foods_stmt->get_result();
$foods = [];
while ($row = $foods_result->fetch_assoc()) {
    $row['img'] = $row['image'] ?: 'placeholder.png';
    $foods[] = $row;
}
$foods_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($canteen['canteen_name']) ?> Menu | UniCanteen</title>
<style>
/* Reuse your dashboard card styles for foods */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;}
.card{background:#fff;border-radius:10px;padding:1rem;box-shadow:0 2px 10px rgba(0,0,0,0.04);text-align:center;transition:transform .15s;cursor:pointer;}
.card:hover{transform:translateY(-6px);}
.card img{width:100%;height:140px;object-fit:cover;border-radius:8px;}
.card h4{margin:0.6rem 0 0.25rem;color:#1e293b;}
.card p{margin:0.25rem 0 0.8rem;color:#475569;font-size:0.95rem;}
.btn{background:#2563eb;color:#fff;border:none;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:700;}
.btn:hover{background:#1d4ed8;}
.btn:disabled{background:#94a3b8;cursor:not-allowed;}

/* ========== FOOTER STYLES ========== */
footer {
  background: #1e293b;
  color: #cbd5e1;
  margin-top: 40px;
  padding: 40px 20px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
}

.footer-content {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 30px;
  margin-bottom: 30px;
}

.footer-section h4 {
  color: #fff;
  font-weight: 700;
  margin: 0 0 15px;
  font-size: 15px;
  font-family: Montserrat, Poppins, sans-serif;
}

.footer-section ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-section li {
  margin-bottom: 10px;
}

.footer-section a {
  color: #cbd5e1;
  text-decoration: none;
  transition: color 0.2s;
}

.footer-section a:hover {
  color: #2563eb;
}

.footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}

@media (max-width: 768px) {
  footer { padding: 30px 15px 15px; }
  .footer-content { grid-template-columns: 1fr; gap: 20px; }
}
</style>
</head>
<body>

<div class="main" style="margin-left:230px;padding:1.5rem;">
  <h2><?= htmlspecialchars($canteen['canteen_name']) ?> Menu</h2>
  <p><?= htmlspecialchars($canteen['description']) ?></p>
  
  <div class="cards">
    <?php if(empty($foods)): ?>
        <p>No foods available yet.</p>
    <?php else: ?>
        <?php foreach($foods as $food): ?>
        <div class="card">
            <img src="<?= htmlspecialchars($food['img']) ?>" alt="<?= htmlspecialchars($food['name']) ?>">
            <h4><?= htmlspecialchars($food['name']) ?></h4>
            <p>₱<?= number_format($food['price'],2) ?></p>
            <?php if ($food['is_available'] && $food['stock_quantity'] > 0): ?>
              <button class="btn">Order Now</button>
            <?php else: ?>
              <button class="btn" disabled>Out of stock</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ========== FOOTER ========== -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h4>About UniCanteen</h4>
      <ul>
        <li><a href="about_us.php">About Us</a></li>
        <li><a href="news.php">News</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="#careers">Careers</a></li>
        <li><a href="#news">News</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>For Students</h4>
      <ul>
        <li><a href="student_dashboard.php">Browse Menus</a></li>
        <li><a href="my_orders.php">My Orders</a></li>
        <li><a href="profile.php">My Profile</a></li>
        <li><a href="student_notifications.php">Notifications</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>Support</h4>
      <ul>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="#help">Help Center</a></li>
        <li><a href="#privacy">Privacy Policy</a></li>
        <li><a href="#terms">Terms of Service</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>Contact Info</h4>
      <ul>
        <li>Email: support@unicanteen.edu</li>
        <li>Phone: +63 (2) 1234-5678</li>
        <li>Hours: Mon-Fri, 7AM-6PM</li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; 2026 UniCanteen System. All rights reserved.</p>
  </div>
</footer>

</body>
</html>

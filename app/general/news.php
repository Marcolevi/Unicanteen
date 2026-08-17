<?php
require_once __DIR__ . '/../includes/common_footer.php';
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

if (!isset($_SESSION['student_id']) && !isset($_SESSION['seller_id'])) {
    header('Location: /Ordering/app/auth/login_register.php');
    exit;
}

$isStudent = isset($_SESSION['student_id']);
$isSeller = isset($_SESSION['seller_id']);
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="/Ordering/">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>News | UniCanteen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;--accent-soft:#eff6ff;--muted:#64748b;--bg:#f8fafc;}
*{box-sizing:border-box}
body{margin:0;font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;background:var(--bg);color:#0f172a;min-height:100vh}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10}
.logo-box{width:100%;padding:20px 0 5px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06)}
.logo-box img{width:125px;height:auto;border-radius:12px;object-fit:contain}
.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;transition:0.15s;border-radius:8px;margin:6px 8px}
.sidebar a:hover,.sidebar a.active{background:rgba(14,165,233,0.15);color:#fff}
.content-wrap{margin-left:230px;padding:28px 24px 40px;}
.main-area{max-width:1100px;margin:0 auto}
.topbar{background:#fff;border-radius:16px;padding:22px 24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:flex-start;box-shadow:0 12px 30px rgba(15,23,42,0.08)}
.topbar h1{margin:0;font-size:2rem;color:#0f172a}
.topbar p{margin:10px 0 0;color:#475569;max-width:540px;line-height:1.8}
.hero-card{background:#fff;border-radius:20px;padding:28px;box-shadow:0 12px 30px rgba(15,23,42,0.08);margin-bottom:24px}
.hero-card h2{margin:0 0 14px;font-size:1.5rem;color:#0f172a}
.hero-card p{margin:0;color:#475569;line-height:1.75}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px}
.info-card{background:#fff;border-radius:18px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,0.06)}
.info-card h3{margin:0 0 14px;font-size:1.1rem;color:#111827}
.info-card p{margin:0;color:#475569;line-height:1.8}
.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:24px}
.metric{background:#fff;border-radius:18px;padding:22px;box-shadow:0 10px 28px rgba(15,23,42,0.06);text-align:center}
.metric strong{display:block;font-size:2rem;color:var(--accent);margin-bottom:8px}
.metric span{color:#475569;font-size:0.95rem}
.footer{margin-top:40px;padding:28px 0 0;color:#64748b}
.footer p{margin:6px 0}
@media(max-width:900px){.topbar{flex-direction:column;gap:16px}.content-wrap{padding:18px} .sidebar{position:static;width:100%;height:auto}}
</style>
</head>
<body>
<div class="sidebar">
  <!-- Logo Container -->
  <div class="logo-box">
    <img src="assets/images/logo2.png" alt="School Logo">
  </div>

  <h2>UniCanteen</h2>
  <?php if ($isStudent): ?>
    <a href="/Ordering/app/student/student_dashboard.php">Main Page</a>
  <?php else: ?>
    <a href="/Ordering/app/seller/seller_dashboard.php">Main Page</a>
  <?php endif; ?>
  <a href="/Ordering/app/general/careers.php">Careers</a>
  <a href="/Ordering/app/general/news.php" class="active">News</a>
  <a href="/Ordering/app/general/about_us.php">About Us</a>
  <a href="#" id="logoutLink">Logout</a>
</div>

<div class="content-wrap">
  <div class="main-area">
    <div class="topbar">
      <div>
        <h1>Latest News</h1>
        <p>Stay informed with the latest news, announcements, system updates, and campus dining events from UniCanteen. We keep students and sellers connected by providing important information, upcoming activities, and new features to enhance the overall campus dining experience.
</p>
      </div>
      <div style="text-align:right;min-width:180px;">
        <strong>Welcome, <?= $full_name ?></strong>
        <p style="margin:10px 0 0;color:#64748b;">Stay Informed</p>
      </div>
    </div>

    <div class="hero-card">
      <h2>Announcements & Updates</h2>
      <p>Get the latest announcements and updates about UniCanteen, including new features, system improvements, canteen schedules, and important campus dining information for students and sellers.
</p>
    </div>

    <div class="info-grid">
      <div class="info-card">
        <h3>New Menu Items</h3>
        <p>Exciting new dishes have been added to our canteens! Check out the fresh options available now.</p>
      </div>
      <div class="info-card">
        <h3>System Updates</h3>
        <p>We've improved the ordering system for faster and smoother experiences. Enjoy the enhanced features!</p>
      </div>
      <div class="info-card">
        <h3>Campus Events</h3>
        <p>Join us for upcoming food festivals and special promotions. Stay tuned for more details.</p>
      </div>
    </div>

    <div class="metric-grid">
      <div class="metric">
        <strong>50+</strong>
        <span>New Items Added This Month</span>
      </div>
      <div class="metric">
        <strong>99%</strong>
        <span>User Satisfaction Rate</span>
      </div>
      <div class="metric">
        <strong>24/7</strong>
        <span>Support Available</span>
      </div>
    </div>

    <div class="footer">
      <p>Need help? Reach our support team at <strong>support@unicanteen.edu</strong>.</p>
      <p>© 2026 UniCanteen System. All rights reserved.</p>
    </div>
  </div>
</div>

<script>
const logoutLink = document.getElementById('logoutLink');
if (logoutLink) {
  logoutLink.addEventListener('click', e => {
    e.preventDefault();
    window.location.href = '/Ordering/app/auth/logout.php';
  });
}
</script>
</body>
</html>

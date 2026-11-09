<?php
require_once __DIR__ . '/../includes/common_footer.php';
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$demandHtml = '';
ob_start(function ($html) use (&$demandHtml) {
    $footer = '<footer class="seller-footer"><div class="seller-footer-content"><section><h4>About UniCanteen</h4><a href="/Ordering/app/general/about_us.php">About Us</a><a href="/Ordering/app/general/news.php">News</a></section><section><h4>Support</h4><a href="#faq">FAQ</a><a href="#help">Help Center</a></section><section><h4>Contact Info</h4><span>support@unicanteen.edu</span><span>Mon–Fri, 7AM–6PM</span></section></div><div class="seller-footer-bottom">© 2026 UniCanteen System. All rights reserved.</div></footer>';
    $oldDemandCard = '<article class="card"><h3>Demand estimate</h3><p class="hint">Estimated tomorrow demand is the average quantity sold per day in the past seven days.</p><p class="hint">Use this as a stock-planning guide, not a guarantee.</p></article>';
    $html = str_replace($oldDemandCard, $demandHtml, $html);
    return str_replace('</main></body>', $footer . '</main></body>', $html);
});
echo '<style>.sidebar a[href="/Ordering/app/auth/logout.php"]{margin-top:auto!important;margin-bottom:14px!important;background:rgba(220,38,38,.16)!important;color:#fecaca!important;border:1px solid rgba(252,165,165,.25)!important;font-weight:600!important}.sidebar a[href="/Ordering/app/auth/logout.php"]:hover{background:#dc2626!important;color:#fff!important}.seller-footer{margin:32px -1.5rem -1.5rem;background:#0d131d;color:#cbd5e1;padding:32px 24px 20px;border-top:2px solid rgba(37,99,235,.2)}.seller-footer-content{max-width:900px;margin:0 auto 24px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:32px}.seller-footer h4{margin:0 0 12px;color:#fff;font:700 15px Montserrat,sans-serif}.seller-footer a,.seller-footer span{display:block;margin:8px 0;color:#cbd5e1;text-decoration:none;font-size:13px}.seller-footer a:hover{color:#60a5fa}.seller-footer-bottom{max-width:900px;margin:auto;padding-top:18px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:#94a3b8;font-size:13px}@media(max-width:850px){.seller-footer{margin:32px -1.5rem -1.5rem}.seller-footer-content{grid-template-columns:1fr}}</style>';
require_once __DIR__ . '/../../config/db_connection.php';
if (!isset($_SESSION['seller_id'])) { header('Location: /Ordering/app/auth/login_register.php'); exit; }
$sellerId = (int)$_SESSION['seller_id'];
function sellerQuery($conn, $sql, $sellerId) { $s=$conn->prepare($sql); $s->bind_param('i',$sellerId); $s->execute(); $r=$s->get_result(); $s->close(); return $r; }
$paidCompleted = "LOWER(status)='completed' AND ((payment_method='cash' AND payment_status='paid') OR (payment_method='gcash_sandbox' AND payment_status IN ('paid','paid_sandbox')))";
$collectedPayment = "((payment_method='cash' AND LOWER(status)='completed' AND payment_status='paid') OR (payment_method='gcash_sandbox' AND payment_status IN ('paid','paid_sandbox') AND LOWER(status)<>'cancelled'))";
$paymentDate = 'COALESCE(payment_confirmed_at, created_at)';
$joinedPaidCompleted = "LOWER(o.status)='completed' AND ((o.payment_method='cash' AND o.payment_status='paid') OR (o.payment_method='gcash_sandbox' AND o.payment_status IN ('paid','paid_sandbox')))";
$joinedPaymentDate = 'COALESCE(o.payment_confirmed_at, o.created_at)';
$summary=sellerQuery($conn,"SELECT COUNT(*) orders_count,COALESCE(SUM(total),0) revenue FROM orders WHERE seller_id=? AND $collectedPayment",$sellerId)->fetch_assoc();
$today=sellerQuery($conn,"SELECT COUNT(*) orders_count,COALESCE(SUM(total),0) revenue FROM orders WHERE seller_id=? AND $collectedPayment AND DATE($paymentDate)=CURDATE()",$sellerId)->fetch_assoc();
$currentWeek=sellerQuery($conn,"SELECT COUNT(*) orders_count FROM orders WHERE seller_id=? AND $collectedPayment AND $paymentDate>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)",$sellerId)->fetch_assoc();
$latestCompleted=sellerQuery($conn,"SELECT MAX(DATE($paymentDate)) completed_date FROM orders WHERE seller_id=? AND $collectedPayment",$sellerId)->fetch_assoc();
$analyticsEnd = ((int)($currentWeek['orders_count'] ?? 0) > 0 || empty($latestCompleted['completed_date'])) ? date('Y-m-d') : $latestCompleted['completed_date'];
$analyticsStart = date('Y-m-d', strtotime($analyticsEnd . ' -6 days'));
$analyticsEndExclusive = date('Y-m-d', strtotime($analyticsEnd . ' +1 day'));
$analyticsPeriodLabel = $analyticsStart === date('Y-m-d', strtotime('-6 days'))
    ? 'the last seven days'
    : 'the most recent seven-day period with collected payments (' . date('M j', strtotime($analyticsStart)) . '–' . date('M j, Y', strtotime($analyticsEnd)) . ')';
$week=sellerQuery($conn,"SELECT COALESCE(SUM(total),0) revenue FROM orders WHERE seller_id=? AND $collectedPayment AND $paymentDate>='$analyticsStart' AND $paymentDate<'$analyticsEndExclusive'",$sellerId)->fetch_assoc();
$foods=sellerQuery($conn,"SELECT f.name,f.stock_quantity,COALESCE(SUM(oi.quantity),0) quantity_sold,COALESCE(SUM(oi.quantity*oi.price),0) revenue,ROUND(COALESCE(SUM(oi.quantity),0)/7,1) daily_estimate FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN foods f ON f.id=oi.food_id WHERE o.seller_id=? AND $joinedPaidCompleted AND $joinedPaymentDate>='$analyticsStart' AND $joinedPaymentDate<'$analyticsEndExclusive' GROUP BY f.id,f.name,f.stock_quantity ORDER BY quantity_sold DESC,revenue DESC LIMIT 10",$sellerId)->fetch_all(MYSQLI_ASSOC);
$revenueByProduct=sellerQuery($conn,"SELECT f.name,COALESCE(SUM(oi.quantity*oi.price),0) revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN foods f ON f.id=oi.food_id WHERE o.seller_id=? AND $joinedPaidCompleted AND $joinedPaymentDate>='$analyticsStart' AND $joinedPaymentDate<'$analyticsEndExclusive' GROUP BY f.id,f.name ORDER BY revenue DESC",$sellerId)->fetch_all(MYSQLI_ASSOC);
$productRevenueTotal = array_sum(array_map(fn($product) => (float)$product['revenue'], $revenueByProduct));
$pieColors = ['#2563eb','#7c3aed','#0891b2','#16a34a','#d97706','#dc2626','#db2777','#4f46e5','#0f766e','#9333ea'];
$pieSlices = [];
$startAngle = -90;
foreach ($revenueByProduct as $index => $product) {
    $percentage = $productRevenueTotal > 0 ? ((float)$product['revenue'] / $productRevenueTotal) * 100 : 0;
    $endAngle = $startAngle + ($percentage / 100 * 360);
    $color = $pieColors[$index % count($pieColors)];
    if ($percentage >= 99.999) {
        $path = 'M 120 20 A 100 100 0 1 1 119.99 20 Z';
    } else {
        $startX = 120 + 100 * cos(deg2rad($startAngle));
        $startY = 120 + 100 * sin(deg2rad($startAngle));
        $endX = 120 + 100 * cos(deg2rad($endAngle));
        $endY = 120 + 100 * sin(deg2rad($endAngle));
        $largeArc = $percentage > 50 ? 1 : 0;
        $path = sprintf('M 120 120 L %.3f %.3f A 100 100 0 %d 1 %.3f %.3f Z', $startX, $startY, $largeArc, $endX, $endY);
    }
    $pieSlices[] = ['name' => $product['name'], 'revenue' => (float)$product['revenue'], 'percentage' => $percentage, 'color' => $color, 'path' => $path];
    $startAngle = $endAngle;
}
$demandHtml = '<article class="card"><h3>Demand estimate</h3><p class="hint">Calculation: quantity sold in ' . htmlspecialchars($analyticsPeriodLabel, ENT_QUOTES, 'UTF-8') . ' &divide; 7, rounded up for tomorrow.</p>';
if (!$foods) {
    $demandHtml .= '<p class="empty">No completed orders in this sales period yet.</p>';
} else {
    $demandHtml .= '<div style="margin-top:12px;max-height:155px;overflow-y:auto">';
    foreach ($foods as $food) {
        $estimate = (int)ceil($food['daily_estimate']);
        $name = htmlspecialchars($food['name'], ENT_QUOTES, 'UTF-8');
        $demandHtml .= '<div style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:12px"><strong style="display:block;font-size:13px">' . $name . '</strong><span style="color:#64748b">' . $food['quantity_sold'] . ' sold &divide; 7 = ' . $food['daily_estimate'] . ' per day</span><br><span style="color:#1d4ed8;font-weight:600">Tomorrow: ' . $estimate . ' serving' . ($estimate === 1 ? '' : 's') . '</span></div>';
    }
    $demandHtml .= '</div>';
}
$demandHtml .= '</article>';
$demandHtml .= '<article class="card revenue-product-card"><h3>Revenue by Product</h3><p class="hint">Paid, completed revenue in ' . htmlspecialchars($analyticsPeriodLabel, ENT_QUOTES, 'UTF-8') . '.</p>';
if (!$pieSlices) {
    $demandHtml .= '<p class="empty">No paid product sales in this period yet.</p>';
} else {
    $demandHtml .= '<div class="revenue-product-layout"><svg class="revenue-pie" viewBox="0 0 240 240" role="img" aria-label="Revenue by product pie chart">';
    foreach ($pieSlices as $slice) {
        $label = $slice['name'] . ': PHP ' . number_format($slice['revenue'], 2) . ' (' . number_format($slice['percentage'], 1) . '%)';
        $demandHtml .= '<path d="' . $slice['path'] . '" fill="' . $slice['color'] . '" tabindex="0" data-name="' . htmlspecialchars($slice['name'], ENT_QUOTES, 'UTF-8') . '" data-revenue="PHP ' . number_format($slice['revenue'], 2) . '" data-percentage="' . number_format($slice['percentage'], 1) . '%"><title>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</title></path>';
    }
    $demandHtml .= '<circle cx="120" cy="120" r="56" fill="#fff"></circle><text x="120" y="114">Total</text><text x="120" y="136">PHP ' . number_format($productRevenueTotal, 2) . '</text></svg><div><div class="product-detail" id="productRevenueDetail"><strong>Select a product</strong>Hover, focus, or tap a pie section to view its revenue share.</div><div class="product-legend">';
    foreach ($pieSlices as $slice) {
        $demandHtml .= '<button type="button" data-name="' . htmlspecialchars($slice['name'], ENT_QUOTES, 'UTF-8') . '" data-revenue="PHP ' . number_format($slice['revenue'], 2) . '" data-percentage="' . number_format($slice['percentage'], 1) . '%"><span class="legend-dot" style="background:' . $slice['color'] . '"></span><span class="legend-name">' . htmlspecialchars($slice['name'], ENT_QUOTES, 'UTF-8') . '</span><span class="legend-amount">PHP ' . number_format($slice['revenue'], 2) . ' · ' . number_format($slice['percentage'], 1) . '%</span></button>';
    }
    $demandHtml .= '</div></div></div>';
}
$demandHtml .= '</article>';
$demandHtml .= '<script>(function(){const detail=document.getElementById("productRevenueDetail");if(!detail)return;function show(item){detail.innerHTML="<strong>"+item.dataset.name+"</strong>"+item.dataset.revenue+" · "+item.dataset.percentage+" of product revenue";}document.querySelectorAll(".revenue-pie path, .product-legend button").forEach(item=>{item.addEventListener("mouseenter",()=>show(item));item.addEventListener("focus",()=>show(item));item.addEventListener("click",()=>show(item));});})();</script>';
$trend=sellerQuery($conn,"SELECT DATE($paymentDate) d,COALESCE(SUM(total),0) revenue FROM orders WHERE seller_id=? AND $collectedPayment AND $paymentDate>='$analyticsStart' AND $paymentDate<'$analyticsEndExclusive' GROUP BY DATE($paymentDate)",$sellerId)->fetch_all(MYSQLI_ASSOC);
$map=[]; foreach($trend as $row) $map[$row['d']] = (float)$row['revenue']; $max=max($map?:[0]); $days=[];
for($i=6;$i>=0;$i--){$d=date('Y-m-d',strtotime($analyticsEnd . " -$i days"));$v=$map[$d]??0;$days[]=['name'=>date('D',strtotime($d)),'value'=>$v,'size'=>$max?max(5,$v/$max*100):5];}
?>
<!doctype html><html lang="en"><head><base href="/Ordering/"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sales Analytics | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;--accent-hover:#1d4ed8;--muted:#64748b;--shadow:0 2px 10px rgba(0,0,0,.06)}*{box-sizing:border-box}body{margin:0;font-family:Poppins,sans-serif;background:#f1f5f9;color:#0f172a;min-height:100vh}.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10}.logo-box{width:100%;padding:20px 0 5px;text-align:center;border-bottom:1px solid rgba(255,255,255,.06)}.logo-box img{width:125px;height:auto;border-radius:12px;object-fit:contain}.sidebar h2{margin:0;text-align:center;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,.06);font-family:Montserrat}.sidebar a{color:#cbd5e1;text-decoration:none;padding:.85rem 1.4rem;display:block;margin:6px 8px;border-radius:8px;transition:.15s}.sidebar a:hover,.sidebar a.active{background:rgba(0,85,255,.12);color:#fff}.main{margin-left:230px;padding:1.5rem;min-height:100vh}.topbar,.metric,.card{background:#fff;border-radius:12px;box-shadow:var(--shadow)}.topbar{padding:.9rem 1.2rem;margin-bottom:1.2rem}.topbar h2{margin:0;font:700 21px Montserrat}.topbar p,.hint{margin:5px 0 0;color:var(--muted);font-size:13px}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:18px}.metric{padding:18px}.metric span{font-size:13px;color:var(--muted)}.metric strong{display:block;font:700 24px Montserrat;margin-top:7px}.grid{display:grid;grid-template-columns:1.65fr 1fr;gap:18px}.card{padding:20px}.card h3{margin:0;font:700 17px Montserrat}.chart{height:205px;display:flex;align-items:flex-end;gap:12px;padding-top:18px;border-bottom:1px solid #e2e8f0}.bar-wrap{flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center}.bar-value{font-size:10px;font-weight:600;color:#1e3a8a;line-height:1.2;margin-bottom:4px;white-space:nowrap}.bar{width:100%;max-width:42px;min-height:5px;border-radius:7px 7px 0 0;background:linear-gradient(#60a5fa,var(--accent))}.bar-wrap small{font-size:11px;color:var(--muted);margin-top:7px}table{width:100%;border-collapse:collapse;font-size:13px;margin-top:14px}th,td{text-align:left;padding:11px 8px;border-bottom:1px solid #e2e8f0}th{font-size:12px;color:var(--muted)}.warning{color:#b45309;font-weight:600}.empty{color:var(--muted);padding:20px 0}@media(max-width:850px){.sidebar{position:static;width:100%;height:auto}.main{margin:0}.metrics,.grid{grid-template-columns:1fr}}
.revenue-product-card{grid-column:1 / -1}.revenue-product-layout{display:grid;grid-template-columns:minmax(240px,320px) minmax(0,1fr);gap:36px;align-items:center;margin-top:14px}.revenue-pie{width:100%;max-width:300px;justify-self:center}.revenue-pie path{stroke:#fff;stroke-width:2;cursor:pointer;transition:opacity .15s,transform .15s;transform-origin:120px 120px}.revenue-pie path:hover,.revenue-pie path:focus{opacity:.82;outline:none;transform:scale(1.03)}.revenue-pie text{font:700 17px Montserrat,sans-serif;fill:#0f172a;text-anchor:middle}.product-detail{padding:12px 14px;border-radius:10px;background:#eff6ff;color:#1e3a8a;font-size:13px;margin-bottom:12px}.product-detail strong{display:block;font:700 16px Montserrat,sans-serif;margin-bottom:4px}.product-legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px 18px}.product-legend button{border:0;background:transparent;text-align:left;display:grid;grid-template-columns:12px 1fr auto;gap:9px;align-items:center;padding:7px 4px;border-radius:7px;color:#334155;cursor:pointer;font:inherit}.product-legend button:hover,.product-legend button:focus{background:#f1f5f9;outline:none}.legend-dot{width:11px;height:11px;border-radius:50%}.legend-name{font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.legend-amount{font-size:12px;font-weight:600}@media(max-width:850px){.revenue-product-layout{grid-template-columns:1fr}}
</style></head><body>
<aside class="sidebar"><div class="logo-box"><img src="assets/images/logo2.png" alt="School Logo"></div><h2>UniCanteen</h2><a href="/Ordering/app/seller/seller_dashboard.php">Food Orders</a><a href="/Ordering/app/seller/seller_seats.php">Manage Tables</a><a href="/Ordering/app/seller/seller_menu.php">Menu & Inventory</a><a class="active" href="/Ordering/app/seller/seller_analytics.php">Sales Analytics</a><a href="/Ordering/app/seller/seller_profile.php">Profile</a><a href="/Ordering/app/auth/logout.php">Logout</a></aside>
<main class="main"><section class="topbar"><h2>Sales Analytics</h2><p>Confirmed payments and seven-day demand estimates. Updates automatically every 30 seconds.</p></section><section class="metrics"><div class="metric"><span>Revenue collected today</span><strong>PHP <?=number_format($today['revenue'],2)?></strong></div><div class="metric"><span>All-time collected revenue</span><strong>PHP <?=number_format($summary['revenue'],2)?></strong></div><div class="metric"><span>Collected payments</span><strong><?= (int)$summary['orders_count']?></strong></div><div class="metric"><span>Revenue in <?=htmlspecialchars($analyticsPeriodLabel)?></span><strong>PHP <?=number_format($week['revenue'],2)?></strong></div></section><section class="grid"><article class="card"><h3>Sales trend</h3><p class="hint">Confirmed payment revenue for <?=htmlspecialchars($analyticsPeriodLabel)?>.</p><div class="chart"><?php foreach($days as $day):?><div class="bar-wrap"><span class="bar-value">PHP <?=number_format($day['value'],2)?></span><div class="bar" style="height:<?=$day['size']?>%"></div><small><?=$day['name']?></small></div><?php endforeach?></div></article><article class="card"><h3>Demand estimate</h3><p class="hint">Estimated tomorrow demand is the average quantity sold per day in the past seven days.</p><p class="hint">Use this as a stock-planning guide, not a guarantee.</p></article></section><section class="card" style="margin-top:18px"><h3>Most ordered foods</h3><p class="hint">Ranked by quantity sold in <?=htmlspecialchars($analyticsPeriodLabel)?>.</p><?php if(!$foods):?><p class="empty">No paid, completed orders in this sales period yet.</p><?php else:?><table><thead><tr><th>Food</th><th>Sold</th><th>Revenue</th><th>Estimated tomorrow</th><th>Current stock</th></tr></thead><tbody><?php foreach($foods as $food):$estimate=(int)ceil($food['daily_estimate']);?><tr><td><?=htmlspecialchars($food['name'])?></td><td><?=$food['quantity_sold']?></td><td>PHP <?=number_format($food['revenue'],2)?></td><td><?=$estimate?> serving<?=$estimate===1?'':'s'?></td><td class="<?=$food['stock_quantity']<$estimate?'warning':''?>"><?=$food['stock_quantity']?><?=$food['stock_quantity']<$estimate?' - restock suggested':''?></td></tr><?php endforeach?></tbody></table><?php endif?></section></main><script>setInterval(function(){if(document.visibilityState==='visible')window.location.reload();},30000);</script></body></html>

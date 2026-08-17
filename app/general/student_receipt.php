<?php
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

if (!isset($_SESSION['student_id'])) { http_response_code(401); exit('Please log in to download this receipt.'); }
$schoolId = $_SESSION['student_id'];
$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) { http_response_code(400); exit('Invalid order.'); }

$student = $conn->prepare('SELECT id, full_name FROM students WHERE id=? OR student_id=? LIMIT 1');
$student->bind_param('is', $schoolId, $schoolId);
$student->execute();
$studentRow = $student->get_result()->fetch_assoc();
$student->close();
if (!$studentRow) { http_response_code(403); exit('Student account not found.'); }

$order = $conn->prepare("SELECT o.*, c.canteen_name FROM orders o JOIN canteens c ON c.id=o.canteen_id WHERE o.id=? AND o.student_id=? AND LOWER(o.status) <> 'cancelled' LIMIT 1");
$order->bind_param('ii', $orderId, $studentRow['id']);
$order->execute();
$orderRow = $order->get_result()->fetch_assoc();
$order->close();
if (!$orderRow) { http_response_code(404); exit('Receipt not available for this order.'); }

$items = $conn->prepare('SELECT f.name, oi.quantity, oi.price FROM order_items oi LEFT JOIN foods f ON f.id=oi.food_id WHERE oi.order_id=?');
$items->bind_param('i', $orderId);
$items->execute();
$itemResult = $items->get_result();

function pdfText($value) {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

$paymentMethod = ($orderRow['payment_method'] ?? 'cash') === 'gcash_sandbox' ? 'GCash' : 'Cash on pickup';
$paymentStatus = ($orderRow['payment_status'] ?? 'unpaid') === 'paid_sandbox' ? 'Payment preview' : 'Unpaid';
$lines = ['UNICANTEEN - ORDER RECEIPT', '----------------------------------------------', 'Order number: #' . $orderRow['id'], 'Student: ' . $studentRow['full_name'], 'Canteen: ' . $orderRow['canteen_name'], 'Order type: ' . ($orderRow['mode'] ?: 'Take-out'), 'Table: ' . ($orderRow['seat_numbers'] ?: '-'), 'Order date: ' . $orderRow['created_at'], '----------------------------------------------', 'ITEMS'];
while ($item = $itemResult->fetch_assoc()) {
    $lines[] = sprintf('%dx %s - PHP %.2f', (int)$item['quantity'], $item['name'] ?: 'Item', (float)$item['price'] * (int)$item['quantity']);
}
$items->close();
$lines = array_merge($lines, ['----------------------------------------------', 'TOTAL: PHP ' . number_format((float)$orderRow['total'], 2), 'Payment method: ' . $paymentMethod, 'Payment status: ' . $paymentStatus]);
if (!empty($orderRow['payment_reference'])) $lines[] = 'Reference: ' . $orderRow['payment_reference'];
$lines[] = 'Present this receipt when collecting your order.';

// Keep the receipt block centered on the PDF page instead of placing it at the left edge.
$receiptWidth = 360;
$receiptLeft = (612 - $receiptWidth) / 2;
$receiptHeight = count($lines) * 16;
$receiptTop = min(720, (792 + $receiptHeight) / 2);
$stream = "BT\n/F1 16 Tf\n" . $receiptLeft . ' ' . $receiptTop . " Td\n(" . pdfText(array_shift($lines)) . ") Tj\n/F1 10 Tf\n";
foreach ($lines as $line) $stream .= "0 -16 Td\n(" . pdfText($line) . ") Tj\n";
$stream .= "ET\n";
$objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream'];
$pdf = "%PDF-1.4\n"; $offsets = [0];
foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= ($number + 1) . " 0 obj\n" . $object . "\nendobj\n"; }
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="unicanteen-order-' . $orderRow['id'] . '-receipt.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;

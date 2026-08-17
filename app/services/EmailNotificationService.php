<?php
require_once __DIR__ . '/../../config/mailer.php';

/**
 * Sends one order-status email and records the delivery result. A previously
 * failed notification is retried; a successful one is never sent twice.
 */
function sendOrderEmailNotification($conn, $studentId, $orderId, $type, $status) {
    $query = $conn->prepare(
        'SELECT s.email, s.full_name, s.email_notifications_enabled, c.canteen_name, o.total
         FROM students s
         JOIN orders o ON o.student_id = s.id
         LEFT JOIN canteens c ON c.id = o.canteen_id
         WHERE s.id = ? AND o.id = ?'
    );
    $query->bind_param('ii', $studentId, $orderId);
    $query->execute();
    $student = $query->get_result()->fetch_assoc();
    $query->close();

    if (!$student || !$student['email'] || !(int) $student['email_notifications_enabled']) {
        return false;
    }

    $subject = "UniCanteen - Order #$orderId $status";
    $message = '<h2>UniCanteen</h2><p>Hello ' . htmlspecialchars($student['full_name']) . ',</p>'
        . '<p>Your order <b>#' . $orderId . '</b> is <b>' . htmlspecialchars($status) . '</b>.</p>'
        . '<p><b>Canteen:</b> ' . htmlspecialchars($student['canteen_name'] ?? 'UniCanteen')
        . '<br><b>Total:</b> PHP ' . number_format((float) $student['total'], 2) . '</p>'
        . '<p>You can view your order in UniCanteen.</p>';

    $existing = $conn->prepare(
        'SELECT id, status FROM email_notifications
         WHERE student_id = ? AND order_id = ? AND notification_type = ? LIMIT 1'
    );
    $existing->bind_param('iis', $studentId, $orderId, $type);
    $existing->execute();
    $notification = $existing->get_result()->fetch_assoc();
    $existing->close();

    if ($notification && $notification['status'] === 'sent') {
        return true;
    }

    if ($notification) {
        $notificationId = (int) $notification['id'];
        $retry = $conn->prepare(
            'UPDATE email_notifications
             SET recipient_email = ?, subject = ?, message = ?, status = "pending", error_message = NULL, sent_at = NULL
             WHERE id = ?'
        );
        $retry->bind_param('sssi', $student['email'], $subject, $message, $notificationId);
        $retry->execute();
        $retry->close();
    } else {
        $insert = $conn->prepare(
            'INSERT INTO email_notifications
             (student_id, order_id, notification_type, recipient_email, subject, message, status)
             VALUES (?, ?, ?, ?, ?, ?, "pending")'
        );
        $insert->bind_param('iissss', $studentId, $orderId, $type, $student['email'], $subject, $message);
        $insert->execute();
        $notificationId = (int) $insert->insert_id;
        $insert->close();
    }

    $required = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_ADDRESS'];
    foreach ($required as $key) {
        if (empty($_ENV[$key])) {
            return recordOrderEmailFailure($conn, $notificationId, 'Email service is not configured.');
        }
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = ($_ENV['SMTP_ENCRYPTION'] ?? 'tls') === 'tls'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int) $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_FROM_ADDRESS'], $_ENV['SMTP_FROM_NAME'] ?? 'UniCanteen');
        $mail->addAddress($student['email'], $student['full_name']);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = "UniCanteen: Order #$orderId is $status.";
        $mail->send();

        $sent = $conn->prepare('UPDATE email_notifications SET status = "sent", error_message = NULL, sent_at = NOW() WHERE id = ?');
        $sent->bind_param('i', $notificationId);
        $sent->execute();
        $sent->close();
        return true;
    } catch (Throwable $exception) {
        error_log('Order email notification failed: ' . $exception->getMessage());
        return recordOrderEmailFailure($conn, $notificationId, 'Email delivery failed. Please retry later.');
    }
}

function recordOrderEmailFailure($conn, $notificationId, $message) {
    $failed = $conn->prepare('UPDATE email_notifications SET status = "failed", error_message = ? WHERE id = ?');
    $failed->bind_param('si', $message, $notificationId);
    $failed->execute();
    $failed->close();
    return false;
}

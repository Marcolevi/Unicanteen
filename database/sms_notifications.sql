-- UniCanteen Semaphore SMS notifications migration
ALTER TABLE students ADD COLUMN sms_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE sms_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  reference_type VARCHAR(20) NOT NULL,
  reference_id INT NOT NULL,
  mobile_number VARCHAR(20) DEFAULT NULL,
  notification_type VARCHAR(50) NOT NULL,
  message VARCHAR(500) NOT NULL,
  provider_message_id VARCHAR(100) DEFAULT NULL,
  status VARCHAR(20) NOT NULL,
  error_message VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME DEFAULT NULL,
  UNIQUE KEY sms_notification_once (student_id, reference_type, reference_id, notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

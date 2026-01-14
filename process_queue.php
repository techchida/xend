<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'MailSender.php';

/**
 * Email Queue Processor
 * This script should be run periodically (e.g., via cron job) to process the email queue
 * Usage: php process_queue.php
 */

$db = Database::getInstance()->getConnection();

// Get pending emails from queue
$stmt = $db->prepare("
    SELECT eq.id, eq.dispatch_id, eq.recipient_id, eq.attempt_count,
           d.subject, d.body, d.from_email, d.reply_to, d.smtp_config_id,
           dr.email, dr.fname, dr.lname,
           sc.host, sc.port, sc.username, sc.password, sc.use_tls, sc.from_name
    FROM email_queue eq
    JOIN dispatches d ON eq.dispatch_id = d.id
    JOIN dispatch_recipients dr ON eq.recipient_id = dr.id
    JOIN smtp_configs sc ON d.smtp_config_id = sc.id
    WHERE eq.status = 'pending'
    AND (eq.next_attempt IS NULL OR eq.next_attempt <= NOW())
    AND eq.attempt_count < eq.max_attempts
    LIMIT 10
");

$stmt->execute();
$result = $stmt->get_result();
$emails = $result->fetch_all(MYSQLI_ASSOC);

echo "Processing " . count($emails) . " emails from queue...\n";

foreach ($emails as $email) {
    try {
        // Initialize mail sender with SMTP config
        $mailSender = new MailSender(
            $email['host'],
            $email['port'],
            $email['username'],
            $email['password'],
            (bool)$email['use_tls'],
            $email['from_email'],
            $email['from_name'] ?? $email['from_email']
        );

        // Send email
        $result = $mailSender->send(
            $email['email'],
            trim($email['fname'] . ' ' . $email['lname']),
            $email['subject'],
            $email['body'],
            $email['reply_to']
        );

        if ($result['success']) {
            // Update queue entry
            $stmt = $db->prepare("UPDATE email_queue SET status = 'sent', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $email['id']);
            $stmt->execute();

            // Update recipient status
            $stmt = $db->prepare("UPDATE dispatch_recipients SET status = 'sent', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $email['recipient_id']);
            $stmt->execute();

            // Update dispatch sent count
            $stmt = $db->prepare("
                UPDATE dispatches
                SET sent_count = (SELECT COUNT(*) FROM dispatch_recipients WHERE dispatch_id = ? AND status = 'sent')
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $email['dispatch_id'], $email['dispatch_id']);
            $stmt->execute();

            echo "✓ Sent to {$email['email']}\n";
        } else {
            // Increment attempt count
            $attemptCount = $email['attempt_count'] + 1;
            $errorMessage = $result['error'] ?? 'Unknown error';

            if ($attemptCount >= 3) {
                // Max attempts reached, mark as failed
                $stmt = $db->prepare("
                    UPDATE email_queue
                    SET status = 'failed', attempt_count = ?, error_message = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('isi', $attemptCount, $errorMessage, $email['id']);
                $stmt->execute();

                // Update recipient status
                $stmt = $db->prepare("
                    UPDATE dispatch_recipients
                    SET status = 'failed', error_message = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('si', $errorMessage, $email['recipient_id']);
                $stmt->execute();

                // Update dispatch failed count
                $stmt = $db->prepare("
                    UPDATE dispatches
                    SET failed_count = (SELECT COUNT(*) FROM dispatch_recipients WHERE dispatch_id = ? AND status = 'failed')
                    WHERE id = ?
                ");
                $stmt->bind_param('ii', $email['dispatch_id'], $email['dispatch_id']);
                $stmt->execute();

                echo "✗ Failed to {$email['email']} (max attempts): $errorMessage\n";
            } else {
                // Schedule retry in 5 minutes
                $stmt = $db->prepare("
                    UPDATE email_queue
                    SET attempt_count = ?, error_message = ?, next_attempt = DATE_ADD(NOW(), INTERVAL 5 MINUTE), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('isi', $attemptCount, $errorMessage, $email['id']);
                $stmt->execute();

                echo "⟳ Retry scheduled for {$email['email']} (attempt {$attemptCount})\n";
            }
        }

    } catch (Exception $e) {
        // Handle general exceptions
        $attemptCount = $email['attempt_count'] + 1;
        $errorMessage = $e->getMessage();

        echo "⚠ Error processing {$email['email']}: $errorMessage\n";

        if ($attemptCount >= 3) {
            $stmt = $db->prepare("
                UPDATE email_queue
                SET status = 'failed', attempt_count = ?, error_message = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $attemptCount, $errorMessage, $email['id']);
            $stmt->execute();

            // Update recipient
            $stmt = $db->prepare("
                UPDATE dispatch_recipients
                SET status = 'failed', error_message = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('si', $errorMessage, $email['recipient_id']);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("
                UPDATE email_queue
                SET attempt_count = ?, error_message = ?, next_attempt = DATE_ADD(NOW(), INTERVAL 5 MINUTE), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $attemptCount, $errorMessage, $email['id']);
            $stmt->execute();
        }
    }
}

// Check if any dispatches are now completed
$stmt = $db->prepare("
    UPDATE dispatches
    SET status = 'completed', completed_at = NOW()
    WHERE status = 'sending'
    AND (SELECT COUNT(*) FROM email_queue WHERE dispatch_id = dispatches.id AND status = 'pending') = 0
");
$stmt->execute();

echo "Done!\n";
?>

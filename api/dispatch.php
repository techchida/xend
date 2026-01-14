<?php
require_once '../config.php';
require_once '../Auth.php';
require_once '../Database.php';
require_once '../MailSender.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$adminId = $auth->getAdminId();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'draft') {
        $data = [
            'admin_id' => $adminId,
            'smtp_config_id' => (int)($_POST['smtp_id'] ?? 0),
            'subject' => $_POST['subject'] ?? '',
            'from_email' => $_POST['from_email'] ?? '',
            'from_name' => $_POST['sender_name'] ?? null,
            'reply_to' => $_POST['reply_to'] ?? null,
            'body' => $_POST['body'] ?? '',
            'status' => 'draft'
        ];

        // Validate required fields
        if (empty($data['subject']) || empty($data['smtp_config_id']) || empty($data['from_email']) || empty($data['body'])) {
            throw new Exception('Required fields are missing'. json_encode($data));
        }

        // Verify SMTP config ownership
        $stmt = $db->prepare("SELECT id, from_name FROM smtp_configs WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $data['smtp_config_id'], $adminId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Invalid SMTP configuration');
        }

        $smtpConfig = $result->fetch_assoc();

        // Insert dispatch record
        $stmt = $db->prepare("
            INSERT INTO dispatches (admin_id, smtp_config_id, subject, from_email, from_name, reply_to, body, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'iissssss',
            $data['admin_id'],
            $data['smtp_config_id'],
            $data['subject'],
            $data['from_email'],
            $data['from_name'],
            $data['reply_to'],
            $data['body'],
            $data['status']
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to save draft');
        }

        $dispatchId = $db->insert_id;

        echo json_encode([
            'success' => true,
            'message' => 'Draft saved successfully',
            'dispatch_id' => $dispatchId
        ]);

    } elseif ($action === 'test') {
        $testEmail = filter_var($_POST['test_email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$testEmail) {
            throw new Exception('Invalid test email address');
        }

        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';
        $fromEmail = $_POST['from_email'] ?? '';

        // Get SMTP configuration
        $stmt = $db->prepare("
            SELECT host, port, username, password, use_tls, from_name
            FROM smtp_configs
            WHERE id = ? AND admin_id = ?
        ");
        $stmt->bind_param('ii', $smtpId, $adminId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Invalid SMTP configuration');
        }

        $smtpConfig = $result->fetch_assoc();

        // Send test email using SMTP configuration
        $mailSender = new MailSender(
            $smtpConfig['host'],
            $smtpConfig['port'],
            $smtpConfig['username'],
            $smtpConfig['password'],
            (bool)$smtpConfig['use_tls'],
            $fromEmail,
            $smtpConfig['from_name'] ?? $fromEmail
        );

        $result = $mailSender->send($testEmail, '', $subject, $body, $_POST['reply_to'] ?? null);

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to send test email');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully'
        ]);

    } elseif ($action === 'dispatch') {
        $smtpId = (int)($_POST['smtp_id'] ?? 0);
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';
        
        // Get SMTP configuration
        $stmt = $db->prepare("
            SELECT from_email FROM smtp_configs
            WHERE id = ? AND admin_id = ?
        ");
        $stmt->bind_param('ii', $smtpId, $adminId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Invalid SMTP configuration');
        }

        $smtpConfig = $result->fetch_assoc();
        $fromEmail = $smtpConfig['from_email'];
        $fromName = $_POST['sender_name'] ?? null;
        $replyTo = $_POST['reply_to'] ?? null;

        if (empty($subject) || empty($smtpId) || empty($fromEmail) || empty($body)) {
            throw new Exception('Required fields are missing'. json_encode([
                'subject' => $subject,
                'smtpId' => $smtpId,
                'fromEmail' => $fromEmail,
                'body' => $body
            ]));
        }

        // Verify SMTP config
        $stmt = $db->prepare("SELECT id FROM smtp_configs WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $smtpId, $adminId);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('Invalid SMTP configuration');
        }

        // Get recipients from testers or CSV
        $recipients = [];

        // Add selected testers
        if (isset($_POST['test_recipients']) && is_array($_POST['test_recipients'])) {
            foreach ($_POST['test_recipients'] as $testerId) {
                $stmt = $db->prepare("SELECT title, fname, lname, email FROM testers WHERE id = ? AND admin_id = ?");
                $stmt->bind_param('ii', $testerId, $adminId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $recipients[] = $row;
                }
            }
        }

        // Add recipients from CSV
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $csvFile = $_FILES['csv_file']['tmp_name'];
            $csvHandle = fopen($csvFile, 'r');

            if ($csvHandle === false) {
                throw new Exception('Failed to open CSV file');
            }

            while (($row = fgetcsv($csvHandle)) !== false) {
                if (count($row) >= 4) {
                    $recipients[] = [
                        'title' => $row[0],
                        'fname' => $row[1],
                        'lname' => $row[2],
                        'email' => $row[3]
                    ];
                }
            }

            fclose($csvHandle);
        }

        if (empty($recipients)) {
            throw new Exception('No recipients found');
        }

        // Create dispatch record
        $dispatchStatus = 'sending';
        $totalRecipients = count($recipients);

        $stmt = $db->prepare("
            INSERT INTO dispatches (admin_id, smtp_config_id, subject, from_email, from_name, reply_to, body, status, total_recipients, started_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            'iissssssi',
            $adminId,
            $smtpId,
            $subject,
            $fromEmail,
            $fromName,
            $replyTo,
            $body,
            $dispatchStatus,
            $totalRecipients
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to create dispatch');
        }

        $dispatchId = $db->insert_id;

        // Create dispatch recipients and queue entries
        $stmt = $db->prepare("
            INSERT INTO dispatch_recipients (dispatch_id, title, fname, lname, email, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $queueStmt = $db->prepare("
            INSERT INTO email_queue (dispatch_id, recipient_id, status)
            VALUES (?, ?, 'pending')
        ");

        foreach ($recipients as $recipient) {
            if (!filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Prepare variables for binding
            $title = $recipient['title'] ?? null;
            $fname = $recipient['fname'];
            $lname = $recipient['lname'];
            $email = $recipient['email'];

            $stmt->bind_param(
                'issss',
                $dispatchId,
                $title,
                $fname,
                $lname,
                $email
            );

            if ($stmt->execute()) {
                $recipientId = $db->insert_id;

                $queueStmt->bind_param('ii', $dispatchId, $recipientId);
                $queueStmt->execute();
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Dispatch started successfully',
            'dispatch_id' => $dispatchId
        ]);

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log('Dispatch error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

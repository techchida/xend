<?php
require_once '../config.php';
require_once '../Auth.php';
require_once '../Database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$adminId = $auth->getAdminId();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'create') {
        $data = [
            'admin_id' => $adminId,
            'name' => $input['name'] ?? '',
            'host' => $input['host'] ?? '',
            'port' => (int)($input['port'] ?? 587),
            'username' => $input['username'] ?? null,
            'password' => $input['password'] ?? null,
            'from_email' => $input['from_email'] ?? '',
            'from_name' => $input['from_name'] ?? null,
            'use_tls' => (bool)($input['use_tls'] ?? true)
        ];

        // Validate required fields
        if (empty($data['name']) || empty($data['host']) || empty($data['from_email'])) {
            throw new Exception('Required fields are missing');
        }

        $stmt = $db->prepare("
            INSERT INTO smtp_configs (admin_id, name, host, port, username, password, from_email, from_name, use_tls)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'issiisssi',
            $data['admin_id'],
            $data['name'],
            $data['host'],
            $data['port'],
            $data['username'],
            $data['password'],
            $data['from_email'],
            $data['from_name'],
            $data['use_tls']
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to create SMTP configuration');
        }

        echo json_encode([
            'success' => true,
            'message' => 'SMTP configuration created successfully',
            'id' => $db->insert_id
        ]);

    } elseif ($action === 'update') {
        $id = (int)($input['smtp_id'] ?? 0);
        if ($id === 0) {
            throw new Exception('Invalid SMTP ID');
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM smtp_configs WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $id, $adminId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('SMTP configuration not found');
        }

        // Prepare variables for binding
        $name = $input['name'] ?? '';
        $host = $input['host'] ?? '';
        $port = (int)($input['port'] ?? 587);
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;
        $from_email = $input['from_email'] ?? '';
        $from_name = $input['from_name'] ?? null;
        $use_tls = (int)($input['use_tls'] ?? 0);

        $stmt = $db->prepare("
            UPDATE smtp_configs
            SET name = ?, host = ?, port = ?, username = ?, password = ?, from_email = ?, from_name = ?, use_tls = ?
            WHERE id = ? AND admin_id = ?
        ");

        $stmt->bind_param(
            'ssissssiii',
            $name,
            $host,
            $port,
            $username,
            $password,
            $from_email,
            $from_name,
            $use_tls,
            $id,
            $adminId
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to update SMTP configuration');
        }

        echo json_encode([
            'success' => true,
            'message' => 'SMTP configuration updated successfully'
        ]);

    } elseif ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id === 0) {
            throw new Exception('Invalid SMTP ID');
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM smtp_configs WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $id, $adminId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('SMTP configuration not found');
        }

        $stmt = $db->prepare("DELETE FROM smtp_configs WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $id, $adminId);

        if (!$stmt->execute()) {
            throw new Exception('Failed to delete SMTP configuration');
        }

        echo json_encode([
            'success' => true,
            'message' => 'SMTP configuration deleted successfully'
        ]);

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

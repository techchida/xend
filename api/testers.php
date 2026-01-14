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
            'title' => $input['title'] ?? null,
            'fname' => $input['fname'] ?? '',
            'lname' => $input['lname'] ?? '',
            'email' => $input['email'] ?? ''
        ];

        // Validate required fields
        if (empty($data['fname']) || empty($data['lname']) || empty($data['email'])) {
            throw new Exception('First name, last name, and email are required');
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        $stmt = $db->prepare("
            INSERT INTO testers (admin_id, title, fname, lname, email)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'issss',
            $data['admin_id'],
            $data['title'],
            $data['fname'],
            $data['lname'],
            $data['email']
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to create test recipient');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Test recipient created successfully',
            'id' => $db->insert_id
        ]);

    } elseif ($action === 'update') {
        $id = (int)($input['tester_id'] ?? 0);
        if ($id === 0) {
            throw new Exception('Invalid tester ID');
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM testers WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $id, $adminId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('Test recipient not found');
        }

        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        $stmt = $db->prepare("
            UPDATE testers
            SET title = ?, fname = ?, lname = ?, email = ?
            WHERE id = ? AND admin_id = ?
        ");

        $stmt->bind_param(
            'ssssii',
            $input['title'],
            $input['fname'],
            $input['lname'],
            $input['email'],
            $id,
            $adminId
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to update test recipient');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Test recipient updated successfully'
        ]);

    } elseif ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id === 0) {
            throw new Exception('Invalid tester ID');
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM testers WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $id, $adminId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('Test recipient not found');
        }

        $stmt = $db->prepare("DELETE FROM testers WHERE id = ? AND admin_id = ?");
        $stmt->bind_param('ii', $id, $adminId);

        if (!$stmt->execute()) {
            throw new Exception('Failed to delete test recipient');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Test recipient deleted successfully'
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

<?php
require_once 'config.php';
require_once 'Database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, password FROM admins WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            $admin = $result->fetch_assoc();

            if (!password_verify($password, $admin['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['login_time'] = time();

            return ['success' => true, 'message' => 'Login successful'];
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during login'];
        }
    }

    public function logout() {
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }

        // Check session timeout
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }

        // Update login time
        $_SESSION['login_time'] = time();

        return true;
    }

    public function getAdminId() {
        return $_SESSION['admin_id'] ?? null;
    }

    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }

    public function changePassword($adminId, $oldPassword, $newPassword) {
        try {
            // Get current password
            $stmt = $this->db->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Admin not found'];
            }

            $admin = $result->fetch_assoc();

            // Verify old password
            if (!password_verify($oldPassword, $admin['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }

            // Validate new password
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }

            // Hash and update new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashedPassword, $adminId);

            if (!$stmt->execute()) {
                throw new Exception('Password update failed');
            }

            return ['success' => true, 'message' => 'Password changed successfully'];
        } catch (Exception $e) {
            error_log('Change password error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }

    public function createAdmin($username, $password) {
        try {
            // Check if admin already exists
            $stmt = $this->db->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                return ['success' => false, 'message' => 'Username already exists'];
            }

            // Validate password
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->bind_param('ss', $username, $hashedPassword);

            if (!$stmt->execute()) {
                throw new Exception('Admin creation failed');
            }

            return ['success' => true, 'message' => 'Admin created successfully'];
        } catch (Exception $e) {
            error_log('Create admin error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }
}
?>

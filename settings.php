<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'Database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$adminId = $auth->getAdminId();
$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } else {
        $result = $auth->changePassword($adminId, $oldPassword, $newPassword);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get SMTP configs
$stmtSmtp = $db->prepare("SELECT * FROM smtp_configs WHERE admin_id = ? ORDER BY created_at DESC");
$stmtSmtp->bind_param('i', $adminId);
$stmtSmtp->execute();
$smtpConfigs = $stmtSmtp->get_result()->fetch_all(MYSQLI_ASSOC);

// Get testers
$stmtTesters = $db->prepare("SELECT * FROM testers WHERE admin_id = ? ORDER BY created_at DESC");
$stmtTesters->bind_param('i', $adminId);
$stmtTesters->execute();
$testers = $stmtTesters->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Mail Dispatch System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100">

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 bg-gradient-to-br from-purple-600 to-blue-600 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Mail Dispatch</h1>
                </div>

                <div class="flex items-center gap-1 md:gap-4">
                    <a href="dashboard.php" class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition font-medium text-sm">
                        📊 Dashboard
                    </a>
                    <a href="compose.php" class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition font-medium text-sm">
                        ✉️ Compose
                    </a>
                    <a href="settings.php" class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100 transition font-medium text-sm">
                        ⚙️ Settings
                    </a>
                    <form method="POST" action="logout.php" style="display: inline;">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition font-medium text-sm">
                            🚪 Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Settings</h2>
            <p class="text-gray-600">Manage SMTP configurations, test recipients, and account security</p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-red-800 text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-green-800 text-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="flex gap-4 mb-8 border-b border-gray-200 overflow-x-auto">
            <button
                class="tab-button active px-6 py-3 font-semibold text-gray-900 border-b-2 border-purple-600 transition"
                onclick="switchTab('smtp')"
            >
                📡 SMTP Configs
            </button>
            <button
                class="tab-button px-6 py-3 font-semibold text-gray-600 border-b-2 border-transparent hover:text-gray-900 transition"
                onclick="switchTab('testers')"
            >
                👥 Test Recipients
            </button>
            <button
                class="tab-button px-6 py-3 font-semibold text-gray-600 border-b-2 border-transparent hover:text-gray-900 transition"
                onclick="switchTab('security')"
            >
                🔒 Security
            </button>
        </div>

        <!-- SMTP Configurations Tab -->
        <div id="smtp" class="tab-content active">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-900">SMTP Configurations</h3>
                <button
                    onclick="openSmtpModal()"
                    class="px-6 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition shadow-lg"
                >
                    + Add SMTP Config
                </button>
            </div>

            <div class="grid gap-6">
                <?php if (empty($smtpConfigs)): ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                        <p class="text-6xl mb-4">📡</p>
                        <p class="text-gray-600 mb-4">No SMTP configurations yet</p>
                        <button
                            onclick="openSmtpModal()"
                            class="px-6 py-2 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 transition inline-block"
                        >
                            Create Your First SMTP Config
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($smtpConfigs as $config): ?>
                        <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-md transition">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($config['name']); ?></h4>
                                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($config['host']); ?>:<?php echo $config['port']; ?></p>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        onclick="editSmtpConfig(<?php echo htmlspecialchars(json_encode($config)); ?>)"
                                        class="px-4 py-2 bg-blue-50 text-blue-700 font-semibold rounded-lg hover:bg-blue-100 transition text-sm"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onclick="deleteSmtpConfig(<?php echo $config['id']; ?>)"
                                        class="px-4 py-2 bg-red-50 text-red-700 font-semibold rounded-lg hover:bg-red-100 transition text-sm"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-600 font-medium">From Email</p>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($config['from_email']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600 font-medium">TLS</p>
                                    <p class="text-gray-900"><?php echo $config['use_tls'] ? '✓ Enabled' : '✗ Disabled'; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Recipients Tab -->
        <div id="testers" class="tab-content hidden">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-900">Test Recipients</h3>
                <button
                    onclick="openTesterModal()"
                    class="px-6 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition shadow-lg"
                >
                    + Add Tester
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <?php if (empty($testers)): ?>
                    <div class="p-12 text-center">
                        <p class="text-6xl mb-4">👥</p>
                        <p class="text-gray-600 mb-4">No test recipients yet</p>
                        <button
                            onclick="openTesterModal()"
                            class="px-6 py-2 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 transition inline-block"
                        >
                            Add Your First Tester
                        </button>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Name</th>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Email</th>
                                    <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Title</th>
                                    <th class="px-8 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($testers as $tester): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-8 py-4 text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($tester['fname'] . ' ' . $tester['lname']); ?>
                                        </td>
                                        <td class="px-8 py-4 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($tester['email']); ?>
                                        </td>
                                        <td class="px-8 py-4 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($tester['title'] ?? '-'); ?>
                                        </td>
                                        <td class="px-8 py-4 text-right flex gap-2 justify-end">
                                            <button
                                                onclick="editTester(<?php echo htmlspecialchars(json_encode($tester)); ?>)"
                                                class="px-4 py-2 bg-blue-50 text-blue-700 font-semibold rounded-lg hover:bg-blue-100 transition text-sm"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onclick="deleteTester(<?php echo $tester['id']; ?>)"
                                                class="px-4 py-2 bg-red-50 text-red-700 font-semibold rounded-lg hover:bg-red-100 transition text-sm"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security" class="tab-content hidden">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Change Password</h3>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 max-w-2xl">
                <form method="POST" action="settings.php" class="space-y-6">
                    <input type="hidden" name="action" value="change_password">

                    <div>
                        <label for="old_password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Current Password *
                        </label>
                        <input
                            type="password"
                            id="old_password"
                            name="old_password"
                            required
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                New Password *
                            </label>
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                required
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            >
                            <p class="text-xs text-gray-600 mt-2">Minimum 8 characters</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Confirm Password *
                            </label>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        id="passwordBtn"
                        class="px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:from-purple-600 disabled:hover:to-blue-600 flex items-center gap-2"
                    >
                        <span class="password-icon">🔐</span>
                        <span class="password-text">Update Password</span>
                        <span class="spinner hidden">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SMTP Modal -->
    <div id="smtpModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-8 max-h-96 overflow-y-auto">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">SMTP Configuration</h2>
            <form id="smtpForm" class="space-y-4">
                <input type="hidden" id="smtpId" name="smtp_id" value="">

                <div>
                    <label for="smtpName" class="block text-sm font-semibold text-gray-700 mb-2">
                        Configuration Name *
                    </label>
                    <input type="text" id="smtpName" name="name" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="smtpHost" class="block text-sm font-semibold text-gray-700 mb-2">
                            SMTP Host *
                        </label>
                        <input type="text" id="smtpHost" name="host" placeholder="smtp.gmail.com" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label for="smtpPort" class="block text-sm font-semibold text-gray-700 mb-2">
                            SMTP Port *
                        </label>
                        <input type="number" id="smtpPort" name="port" value="587" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div>
                    <label for="smtpUsername" class="block text-sm font-semibold text-gray-700 mb-2">
                        SMTP Username
                    </label>
                    <input type="text" id="smtpUsername" name="username" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div>
                    <label for="smtpPassword" class="block text-sm font-semibold text-gray-700 mb-2">
                        SMTP Password
                    </label>
                    <input type="password" id="smtpPassword" name="password" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div>
                    <label for="fromEmail" class="block text-sm font-semibold text-gray-700 mb-2">
                        From Email *
                    </label>
                    <input type="email" id="fromEmail" name="from_email" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div>
                    <label for="fromName" class="block text-sm font-semibold text-gray-700 mb-2">
                        From Name
                    </label>
                    <input type="text" id="fromName" name="from_name" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="smtpTLS" name="use_tls" value="1" checked class="w-4 h-4 rounded border-gray-300">
                    <label for="smtpTLS" class="text-sm font-medium text-gray-700">Use TLS</label>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeSmtpModal()" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="smtpSubmitBtn" class="flex-1 px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:from-purple-600 disabled:hover:to-blue-600 flex items-center justify-center gap-2">
                        <span>Save SMTP Config</span>
                        <span class="spinner hidden">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tester Modal -->
    <div id="testerModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Test Recipient</h2>
            <form id="testerForm" class="space-y-4">
                <input type="hidden" id="testerId" name="tester_id" value="">

                <div>
                    <label for="testerTitle" class="block text-sm font-semibold text-gray-700 mb-2">
                        Title
                    </label>
                    <input type="text" id="testerTitle" name="title" placeholder="Mr., Ms., Dr., etc." class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="testerFname" class="block text-sm font-semibold text-gray-700 mb-2">
                            First Name *
                        </label>
                        <input type="text" id="testerFname" name="fname" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label for="testerLname" class="block text-sm font-semibold text-gray-700 mb-2">
                            Last Name *
                        </label>
                        <input type="text" id="testerLname" name="lname" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div>
                    <label for="testerEmail" class="block text-sm font-semibold text-gray-700 mb-2">
                        Email *
                    </label>
                    <input type="email" id="testerEmail" name="email" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeTesterModal()" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="testerSubmitBtn" class="flex-1 px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:from-purple-600 disabled:hover:to-blue-600 flex items-center justify-center gap-2">
                        <span>Save Tester</span>
                        <span class="spinner hidden">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Loading state helper
        function setLoading(buttonId, isLoading = true) {
            const btn = document.getElementById(buttonId);
            if (!btn) return;

            btn.disabled = isLoading;
            const spinner = btn.querySelector('.spinner');
            const text = btn.querySelector('span:not(.spinner)');

            if (isLoading) {
                spinner?.classList.remove('hidden');
                if (text) text.style.display = 'none';
            } else {
                spinner?.classList.add('hidden');
                if (text) text.style.display = 'inline';
            }
        }

        function resetLoading(buttonId) {
            setLoading(buttonId, false);
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(el => {
                el.classList.remove('border-purple-600', 'text-gray-900');
                el.classList.add('border-transparent', 'text-gray-600');
            });

            document.getElementById(tabName).classList.remove('hidden');
            event.target.classList.remove('border-transparent', 'text-gray-600');
            event.target.classList.add('border-purple-600', 'text-gray-900');
        }

        function openSmtpModal() {
            // Only reset if creating new (smtpId is empty)
            if (!document.getElementById('smtpId').value) {
                document.getElementById('smtpForm').reset();
            }
            document.getElementById('smtpModal').classList.remove('hidden');
        }

        function closeSmtpModal() {
            document.getElementById('smtpModal').classList.add('hidden');
            document.getElementById('smtpId').value = '';
        }

        function openTesterModal() {
            // Only reset if creating new (testerId is empty)
            if (!document.getElementById('testerId').value) {
                document.getElementById('testerForm').reset();
            }
            document.getElementById('testerModal').classList.remove('hidden');
        }

        function closeTesterModal() {
            document.getElementById('testerModal').classList.add('hidden');
            document.getElementById('testerId').value = '';
        }

        function deleteSmtpConfig(id) {
            if (confirm('Are you sure you want to delete this SMTP configuration?')) {
                fetch('api/smtp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                });
            }
        }

        function deleteTester(id) {
            if (confirm('Are you sure you want to delete this test recipient?')) {
                fetch('api/testers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                });
            }
        }

        function editSmtpConfig(config) {
            document.getElementById('smtpId').value = config.id;
            document.getElementById('smtpName').value = config.name;
            document.getElementById('smtpHost').value = config.host;
            document.getElementById('smtpPort').value = config.port;
            document.getElementById('smtpUsername').value = config.username || '';
            document.getElementById('smtpPassword').value = config.password || '';
            document.getElementById('fromEmail').value = config.from_email;
            document.getElementById('fromName').value = config.from_name || '';
            document.getElementById('smtpTLS').checked = config.use_tls;
            openSmtpModal();
        }

        function editTester(tester) {
            document.getElementById('testerId').value = tester.id;
            document.getElementById('testerTitle').value = tester.title || '';
            document.getElementById('testerFname').value = tester.fname;
            document.getElementById('testerLname').value = tester.lname;
            document.getElementById('testerEmail').value = tester.email;
            openTesterModal();
        }

        document.getElementById('smtpForm').addEventListener('submit', function(e) {
            e.preventDefault();

            setLoading('smtpSubmitBtn');

            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            data.action = document.getElementById('smtpId').value ? 'update' : 'create';

            fetch('api/smtp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSmtpModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    resetLoading('smtpSubmitBtn');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                resetLoading('smtpSubmitBtn');
            });
        });

        document.getElementById('testerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            setLoading('testerSubmitBtn');

            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            data.action = document.getElementById('testerId').value ? 'update' : 'create';

            fetch('api/testers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeTesterModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    resetLoading('testerSubmitBtn');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                resetLoading('testerSubmitBtn');
            });
        });

        // Handle password form loading state
        document.querySelector('form[action="settings.php"]').addEventListener('submit', function() {
            setLoading('passwordBtn');
        });

        window.onclick = function(event) {
            const smtpModal = document.getElementById('smtpModal');
            const testerModal = document.getElementById('testerModal');
            if (event.target == smtpModal) closeSmtpModal();
            if (event.target == testerModal) closeTesterModal();
        }
    </script>

</body>
</html>

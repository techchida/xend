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

// Get dispatch ID if viewing specific dispatch
$dispatchId = isset($_GET['dispatch_id']) ? (int)$_GET['dispatch_id'] : null;
$viewingDispatch = false;
$dispatch = null;
$recipients = [];

if ($dispatchId) {
    $stmt = $db->prepare("
        SELECT d.*, s.name as smtp_name
        FROM dispatches d
        LEFT JOIN smtp_configs s ON d.smtp_config_id = s.id
        WHERE d.id = ? AND d.admin_id = ?
    ");
    $stmt->bind_param('ii', $dispatchId, $adminId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $dispatch = $result->fetch_assoc();
        $viewingDispatch = true;

        $stmt = $db->prepare("
            SELECT id, title, fname, lname, email, status, opened, clicked, opened_at, clicked_at
            FROM dispatch_recipients
            WHERE dispatch_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $dispatchId);
        $stmt->execute();
        $recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        header('Location: dashboard.php');
        exit;
    }
} else {
    $stmt = $db->prepare("
        SELECT d.*, s.name as smtp_name
        FROM dispatches d
        LEFT JOIN smtp_configs s ON d.smtp_config_id = s.id
        WHERE d.admin_id = ?
        ORDER BY d.created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $dispatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStatusBadgeClass($status) {
    $classes = [
        'draft' => 'bg-gray-100 text-gray-800',
        'scheduled' => 'bg-blue-100 text-blue-800',
        'sending' => 'bg-amber-100 text-amber-800',
        'completed' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800'
    ];
    return $classes[$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusIcon($status) {
    $icons = [
        'draft' => '📝',
        'scheduled' => '⏰',
        'sending' => '📤',
        'completed' => '✅',
        'failed' => '❌'
    ];
    return $icons[$status] ?? '❓';
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('M d, Y H:i', strtotime($date));
}

function getPercentage($sent, $total) {
    if ($total == 0) return 0;
    return round(($sent / $total) * 100);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $viewingDispatch ? 'Dispatch Details' : 'Dashboard'; ?> - Mail Dispatch System</title>
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if ($viewingDispatch && $dispatch): ?>
            <!-- Dispatch Details View -->

            <!-- Header with Back Button -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <a href="dashboard.php" class="text-purple-600 hover:text-purple-700 text-sm font-semibold mb-2 inline-flex items-center gap-1">
                        ← Back to Dashboard
                    </a>
                    <h2 class="text-3xl font-bold text-gray-900">Campaign Details</h2>
                </div>
                <div class="text-right">
                    <div class="inline-block <?php echo getStatusBadgeClass($dispatch['status']); ?> px-4 py-2 rounded-lg font-semibold">
                        <?php echo getStatusIcon($dispatch['status']); ?> <?php echo ucfirst($dispatch['status']); ?>
                    </div>
                </div>
            </div>

            <!-- Campaign Info Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($dispatch['subject']); ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600 font-medium">From Email</p>
                                <p class="text-lg text-gray-900 font-semibold"><?php echo htmlspecialchars($dispatch['from_email']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 font-medium">Reply To</p>
                                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($dispatch['reply_to'] ?? '-'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 font-medium">SMTP Config</p>
                                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($dispatch['smtp_name'] ?? '-'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600 font-medium">Created</p>
                                <p class="text-lg text-gray-900"><?php echo formatDate($dispatch['created_at']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 font-medium">Started</p>
                                <p class="text-lg text-gray-900"><?php echo formatDate($dispatch['started_at']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 font-medium">Completed</p>
                                <p class="text-lg text-gray-900"><?php echo formatDate($dispatch['completed_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <div class="bg-white rounded-xl border border-gray-200 p-6 hover:border-blue-300 transition">
                    <p class="text-gray-600 text-sm font-medium mb-2">Total Recipients</p>
                    <p class="text-4xl font-bold text-gray-900"><?php echo $dispatch['total_recipients']; ?></p>
                </div>
                <div class="bg-white rounded-xl border border-green-200 p-6 hover:border-green-400 transition">
                    <p class="text-green-600 text-sm font-medium mb-2">✅ Sent</p>
                    <p class="text-4xl font-bold text-green-600"><?php echo $dispatch['sent_count']; ?></p>
                    <p class="text-xs text-gray-600 mt-2"><?php echo getPercentage($dispatch['sent_count'], $dispatch['total_recipients']); ?>%</p>
                </div>
                <div class="bg-white rounded-xl border border-red-200 p-6 hover:border-red-400 transition">
                    <p class="text-red-600 text-sm font-medium mb-2">❌ Failed</p>
                    <p class="text-4xl font-bold text-red-600"><?php echo $dispatch['failed_count']; ?></p>
                    <p class="text-xs text-gray-600 mt-2"><?php echo getPercentage($dispatch['failed_count'], $dispatch['total_recipients']); ?>%</p>
                </div>
                <div class="bg-white rounded-xl border border-amber-200 p-6 hover:border-amber-400 transition">
                    <p class="text-amber-600 text-sm font-medium mb-2">👁️ Opened</p>
                    <p class="text-4xl font-bold text-amber-600"><?php echo $dispatch['opened_count']; ?></p>
                    <p class="text-xs text-gray-600 mt-2"><?php echo getPercentage($dispatch['opened_count'], $dispatch['sent_count']); ?>%</p>
                </div>
                <div class="bg-white rounded-xl border border-purple-200 p-6 hover:border-purple-400 transition">
                    <p class="text-purple-600 text-sm font-medium mb-2">🔗 Clicked</p>
                    <p class="text-4xl font-bold text-purple-600"><?php echo $dispatch['clicked_count']; ?></p>
                    <p class="text-xs text-gray-600 mt-2"><?php echo getPercentage($dispatch['clicked_count'], $dispatch['sent_count']); ?>%</p>
                </div>
            </div>

            <!-- Recipients Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900">Recipients (<?php echo count($recipients); ?>)</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Name</th>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Email</th>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Opened</th>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Clicked</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recipients as $recipient): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-4 text-sm font-medium text-gray-900">
                                        <?php
                                        $name = trim(($recipient['title'] ? $recipient['title'] . ' ' : '') . $recipient['fname'] . ' ' . $recipient['lname']);
                                        echo htmlspecialchars($name);
                                        ?>
                                    </td>
                                    <td class="px-8 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($recipient['email']); ?></td>
                                    <td class="px-8 py-4 text-sm">
                                        <?php if ($recipient['status'] === 'sent'): ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">✅ Sent</span>
                                        <?php elseif ($recipient['status'] === 'failed'): ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">❌ Failed</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">⏳ Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-4 text-sm">
                                        <?php if ($recipient['opened']): ?>
                                            <div class="flex items-center gap-2">
                                                <span class="text-green-600">✓</span>
                                                <span class="text-gray-600 text-xs"><?php echo date('M d H:i', strtotime($recipient['opened_at'])); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-4 text-sm">
                                        <?php if ($recipient['clicked']): ?>
                                            <div class="flex items-center gap-2">
                                                <span class="text-green-600">✓</span>
                                                <span class="text-gray-600 text-xs"><?php echo date('M d H:i', strtotime($recipient['clicked_at'])); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($recipients)): ?>
                    <div class="px-8 py-12 text-center">
                        <p class="text-gray-600">No recipients found for this dispatch</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Dashboard Overview -->

            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Dashboard</h2>
                    <p class="text-gray-600 mt-1">View and manage your email campaigns</p>
                </div>
                <a href="compose.php" class="bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all shadow-lg hover:shadow-xl">
                    + New Campaign
                </a>
            </div>

            <!-- Campaigns Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Subject</th>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                                <th class="px-8 py-4 text-center text-sm font-semibold text-gray-700">Recipients</th>
                                <th class="px-8 py-4 text-center text-sm font-semibold text-gray-700">Sent</th>
                                <th class="px-8 py-4 text-center text-sm font-semibold text-gray-700">Failed</th>
                                <th class="px-8 py-4 text-center text-sm font-semibold text-gray-700">Opened</th>
                                <th class="px-8 py-4 text-left text-sm font-semibold text-gray-700">Created</th>
                                <th class="px-8 py-4 text-right text-sm font-semibold text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($dispatches as $d): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-4">
                                        <p class="font-semibold text-gray-900 max-w-xs truncate"><?php echo htmlspecialchars(substr($d['subject'], 0, 50)); ?></p>
                                    </td>
                                    <td class="px-8 py-4">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold <?php echo getStatusBadgeClass($d['status']); ?>">
                                            <?php echo getStatusIcon($d['status']); ?> <?php echo ucfirst($d['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-4 text-center text-sm font-medium text-gray-900"><?php echo $d['total_recipients']; ?></td>
                                    <td class="px-8 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-50 text-green-700 rounded-lg text-sm font-semibold">
                                            ✓ <?php echo $d['sent_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-50 text-red-700 rounded-lg text-sm font-semibold">
                                            ✕ <?php echo $d['failed_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-4 text-center text-sm font-medium text-gray-900"><?php echo $d['opened_count']; ?></td>
                                    <td class="px-8 py-4 text-sm text-gray-600"><?php echo formatDate($d['created_at']); ?></td>
                                    <td class="px-8 py-4 text-right">
                                        <a href="dashboard.php?dispatch_id=<?php echo $d['id']; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg font-semibold text-sm transition">
                                            View Details →
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($dispatches)): ?>
                    <div class="px-8 py-16 text-center">
                        <p class="text-6xl mb-4">📬</p>
                        <p class="text-gray-600 text-lg mb-6">No campaigns yet</p>
                        <a href="compose.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-purple-700 hover:to-blue-700 transition">
                            Create Your First Campaign →
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

</body>
</html>

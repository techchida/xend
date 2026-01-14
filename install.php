<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'Auth.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Check database connection
$dbConnected = false;
try {
    $db = Database::getInstance()->getConnection();
    $dbConnected = true;
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbConnected) {
    if ($step === 1) {
        // Create tables
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $queries = explode(';', $sql);

            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    if (!$db->query($query)) {
                        $error = 'Error creating tables: ' . $db->error;
                        break;
                    }
                }
            }

            if (empty($error)) {
                $success = 'Database tables created successfully!';
                $step = 2;
            }
        } else {
            $error = 'database.sql file not found';
        }
    } elseif ($step === 2) {
        // Create admin user
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($username)) {
            $error = 'Username is required';
        } elseif (empty($password)) {
            $error = 'Password is required';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        } else {
            $auth = new Auth();
            $result = $auth->createAdmin($username, $password);

            if ($result['success']) {
                $success = $result['message'];
                $step = 3;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Mail Dispatch System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-xl">
        <!-- Card -->
        <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl p-8 md:p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-600 to-blue-600 rounded-2xl mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Mail Dispatch Setup</h1>
                <p class="text-gray-600">Professional Email Campaign Manager</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-gray-700">Step <?php echo $step; ?> of 3</span>
                    <span class="text-sm font-semibold text-gray-700"><?php echo round(($step / 3) * 100); ?>%</span>
                </div>
                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-purple-600 to-blue-600 transition-all duration-300" style="width: <?php echo ($step / 3) * 100; ?>%"></div>
                </div>
            </div>

            <!-- Error/Success Messages -->
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

            <!-- Step 1: Database Setup -->
            <?php if ($step === 1): ?>
                <div class="text-center mb-6">
                    <p class="text-6xl mb-4">🗄️</p>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Initialize Database</h2>
                    <p class="text-gray-600">Create all necessary database tables for the Mail Dispatch System</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-900">
                        <span class="font-semibold">ℹ️ Info:</span> This step will create the database schema with all required tables.
                    </p>
                </div>

                <form method="POST" action="install.php">
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-blue-700 transform hover:scale-105 transition-all duration-200 shadow-lg"
                    >
                        Create Database Tables
                    </button>
                </form>

            <!-- Step 2: Create Admin Account -->
            <?php elseif ($step === 2): ?>
                <div class="text-center mb-6">
                    <p class="text-6xl mb-4">🔐</p>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Create Admin Account</h2>
                    <p class="text-gray-600">Set up your first admin user to access the system</p>
                </div>

                <form method="POST" action="install.php" class="space-y-5">
                    <input type="hidden" name="step" value="2">

                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                            Admin Username
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            autofocus
                            placeholder="e.g., admin"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Password
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            placeholder="Minimum 8 characters"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                        >
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            required
                            placeholder="Re-enter password"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-blue-700 transform hover:scale-105 transition-all duration-200 shadow-lg"
                    >
                        Create Admin Account
                    </button>
                </form>

            <!-- Step 3: Completion -->
            <?php elseif ($step === 3): ?>
                <div class="text-center">
                    <p class="text-6xl mb-4">✨</p>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Installation Complete!</h2>
                    <p class="text-gray-600 mb-8">Your Mail Dispatch System is ready to use</p>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
                        <p class="text-sm text-green-900 font-medium">
                            🎉 All setup steps completed successfully!
                        </p>
                    </div>

                    <a
                        href="login.php"
                        class="inline-block w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-blue-700 transform hover:scale-105 transition-all duration-200 shadow-lg text-center mb-4"
                    >
                        Go to Login
                    </a>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-left">
                        <h3 class="font-bold text-gray-900 mb-4">📋 Next Steps</h3>
                        <ol class="space-y-3 text-sm text-gray-700">
                            <li class="flex gap-3">
                                <span class="font-bold text-purple-600 flex-shrink-0">1.</span>
                                <span>Login with your admin credentials</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="font-bold text-purple-600 flex-shrink-0">2.</span>
                                <span>Add SMTP configurations in Settings</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="font-bold text-purple-600 flex-shrink-0">3.</span>
                                <span>Add test recipients for testing</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="font-bold text-purple-600 flex-shrink-0">4.</span>
                                <span>Create and send your first email campaign</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="font-bold text-purple-600 flex-shrink-0">5.</span>
                                <span>Set up cron job for automated email processing</span>
                            </li>
                        </ol>
                    </div>
                </div>

            <?php else: ?>
                <div class="text-center">
                    <p class="text-6xl mb-4">❌</p>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Invalid Installation Step</h2>
                    <a
                        href="install.php"
                        class="inline-block bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-purple-700 hover:to-blue-700 transition"
                    >
                        Start Over
                    </a>
                </div>
            <?php endif; ?>

            <!-- Database Connection Warning -->
            <?php if (!$dbConnected): ?>
                <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-900 text-sm font-medium">
                        <span class="font-bold">⚠️ Database Connection Error:</span><br>
                        Please check your database configuration in <code class="bg-yellow-100 px-2 py-1 rounded text-yellow-700 font-mono">config.php</code>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-white/60 text-sm">
                Version 1.0 • Mail Dispatch System
            </p>
        </div>
    </div>
</body>
</html>

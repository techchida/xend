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

// Get SMTP configs and testers
$stmtSmtp = $db->prepare("SELECT id, name, from_email, from_name FROM smtp_configs WHERE admin_id = ? ORDER BY name");
$stmtSmtp->bind_param('i', $adminId);
$stmtSmtp->execute();
$smtpConfigs = $stmtSmtp->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtTesters = $db->prepare("SELECT id, title, fname, lname, email FROM testers WHERE admin_id = ? ORDER BY fname");
$stmtTesters->bind_param('i', $adminId);
$stmtTesters->execute();
$testers = $stmtTesters->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email - Mail Dispatch System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
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
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Compose New Campaign</h2>
            <p class="text-gray-600 mb-8">Create and send email campaigns to your recipients</p>
        </div>

        <form id="composeForm" class="space-y-8">
            <!-- Email Details Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Email Details</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-gray-700 mb-2">
                            Subject *
                        </label>
                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            required
                            placeholder="Enter email subject"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                        >
                    </div>

                    <div>
                        <label for="smtpId" class="block text-sm font-semibold text-gray-700 mb-2">
                            SMTP Config *
                        </label>
                        <select
                            id="smtpId"
                            name="smtp_id"
                            required
                            onchange="updateFromEmail()"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                        >
                            <option value="">Select SMTP Configuration</option>
                            <?php foreach ($smtpConfigs as $config): ?>
                                <option value="<?php echo $config['id']; ?>" data-from-email="<?php echo htmlspecialchars($config['from_email']); ?>" data-from-name="<?php echo htmlspecialchars($config['from_name'] ?? ''); ?>"><?php echo htmlspecialchars($config['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($smtpConfigs)): ?>
                            <p class="text-sm text-amber-600 mt-2">
                                ⚠️ No SMTP configurations found. <a href="settings.php" class="underline font-semibold">Create one in Settings</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="fromEmail" class="block text-sm font-semibold text-gray-700 mb-2">
                            From Email
                        </label>
                        <input
                            type="email"
                            id="fromEmail"
                            name="from_email"
                            readonly
                            placeholder="Will be set from SMTP config"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-gray-100 text-gray-600 cursor-not-allowed"
                        >
                        <p class="text-xs text-gray-500 mt-2">Set automatically from SMTP configuration</p>
                    </div>

                    <div>
                        <label for="senderName" class="block text-sm font-semibold text-gray-700 mb-2">
                            Sender Name (Optional)
                        </label>
                        <input
                            type="text"
                            id="senderName"
                            name="sender_name"
                            placeholder="Your Company Name"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                        >
                        <p class="text-xs text-gray-500 mt-2">Display name in recipient inbox</p>
                    </div>
                </div>

                <div>
                    <label for="replyTo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Reply To (Optional)
                    </label>
                    <input
                        type="email"
                        id="replyTo"
                        name="reply_to"
                        placeholder="reply@example.com"
                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition bg-gray-50 focus:bg-white"
                    >
                </div>
            </div>

            <!-- Email Body Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Email Body</h3>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Message Content *
                </label>
                <textarea id="emailBody" name="body" required style="min-height: 400px;"></textarea>
            </div>

            <!-- Recipients Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Recipients</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Test Recipients -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4">Test Recipients</h4>
                        <div class="border border-gray-300 rounded-lg p-4 space-y-3 max-h-64 overflow-y-auto bg-gray-50">
                            <?php if (empty($testers)): ?>
                                <p class="text-gray-600 text-sm">
                                    No test recipients yet. <a href="settings.php" class="text-purple-600 font-semibold">Add one in Settings</a>
                                </p>
                            <?php else: ?>
                                <?php foreach ($testers as $tester): ?>
                                    <label class="flex items-start gap-3 cursor-pointer hover:bg-gray-100 p-2 rounded transition">
                                        <input
                                            type="checkbox"
                                            name="test_recipients"
                                            value="<?php echo $tester['id']; ?>"
                                            data-email="<?php echo $tester['email']; ?>"
                                            class="tester-checkbox mt-1"
                                        >
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($tester['fname'] . ' ' . $tester['lname']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($tester['email']); ?>
                                            </p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- CSV Upload -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4">Or Upload CSV</h4>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-purple-400 transition cursor-pointer bg-gray-50">
                            <input
                                type="file"
                                id="csvFile"
                                name="csv_file"
                                accept=".csv"
                                class="w-full"
                            >
                            <p class="text-sm text-gray-600 mt-2">
                                📄 CSV format: title, fname, lname, email
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Selected Recipients -->
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-4">
                        Selected Recipients: <span id="recipientCount" class="text-purple-600">0</span>
                    </h4>
                    <ul id="recipientsList" class="space-y-2">
                        <!-- Recipients will be added here -->
                    </ul>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 flex-wrap">
                <button
                    type="button"
                    id="testBtn"
                    onclick="sendTest()"
                    class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-gray-100 flex items-center gap-2"
                >
                    <span class="email-icon">📧</span>
                    <span class="email-text">Send Test Email</span>
                    <span class="spinner hidden">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
                <button
                    type="button"
                    id="draftBtn"
                    onclick="saveDraft()"
                    class="px-6 py-3 bg-blue-50 text-blue-700 font-semibold rounded-lg hover:bg-blue-100 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-50 flex items-center gap-2"
                >
                    <span class="draft-icon">💾</span>
                    <span class="draft-text">Save as Draft</span>
                    <span class="spinner hidden">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
                <button
                    type="button"
                    id="dispatchBtn"
                    onclick="startDispatch()"
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:from-purple-600 disabled:hover:to-blue-600 flex items-center gap-2"
                >
                    <span class="dispatch-icon">🚀</span>
                    <span class="dispatch-text">Start Dispatch</span>
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

    <!-- Test Email Modal -->
    <div id="testEmailModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Send Test Email</h2>
            <form id="testEmailForm" class="space-y-4">
                <div>
                    <label for="testEmail" class="block text-sm font-semibold text-gray-700 mb-2">
                        Test Email Address *
                    </label>
                    <input
                        type="email"
                        id="testEmail"
                        name="test_email"
                        required
                        placeholder="test@example.com"
                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    >
                </div>
                <div class="flex gap-4 pt-4">
                    <button
                        type="button"
                        onclick="closeTestModal()"
                        class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition"
                    >
                        Send Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize CKEditor
        CKEDITOR.replace('emailBody', {
            toolbar: [
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                { name: 'paragraph', items: ['BulletedList', 'NumberedList', '-', 'Outdent', 'Indent'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
                { name: 'styles', items: ['Format', 'Font', 'FontSize'] },
                { name: 'colors', items: ['TextColor', 'BGColor'] },
                { name: 'tools', items: ['Source'] }
            ],
            height: 400,
            contentsCss: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
        });

        let selectedRecipients = [];

        // Update From Email when SMTP Config is selected
        function updateFromEmail() {
            const select = document.getElementById('smtpId');
            const selectedOption = select.options[select.selectedIndex];
            const fromEmail = selectedOption.getAttribute('data-from-email');
            const fromName = selectedOption.getAttribute('data-from-name');

            document.getElementById('fromEmail').value = fromEmail || '';
            document.getElementById('senderName').value = fromName || '';
        }

        // Loading state helper
        function setLoading(buttonId, isLoading = true) {
            const btn = document.getElementById(buttonId);
            if (!btn) return;

            btn.disabled = isLoading;
            const spinner = btn.querySelector('.spinner');
            const icon = btn.querySelector('[class$="-icon"]');
            const text = btn.querySelector('[class$="-text"]');

            if (isLoading) {
                spinner?.classList.remove('hidden');
                icon?.classList.add('hidden');
            } else {
                spinner?.classList.add('hidden');
                icon?.classList.remove('hidden');
            }
        }

        function resetLoading(buttonId) {
            setLoading(buttonId, false);
        }

        // Handle tester checkbox changes
        document.querySelectorAll('.tester-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateRecipientsList);
        });

        function updateRecipientsList() {
            selectedRecipients = [];

            // Get selected testers
            document.querySelectorAll('.tester-checkbox:checked').forEach(checkbox => {
                selectedRecipients.push({
                    id: checkbox.value,
                    email: checkbox.dataset.email,
                    type: 'tester'
                });
            });

            renderRecipientsList();
        }

        function renderRecipientsList() {
            const list = document.getElementById('recipientsList');
            const count = document.getElementById('recipientCount');

            list.innerHTML = '';
            count.textContent = selectedRecipients.length;

            if (selectedRecipients.length === 0) {
                list.innerHTML = '<li class="text-gray-500 text-sm">No recipients selected</li>';
                return;
            }

            selectedRecipients.forEach((recipient, index) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200';
                li.innerHTML = `
                    <span class="text-sm text-gray-700">${recipient.email}</span>
                    <button type="button" onclick="removeRecipient(${index})" class="text-red-600 hover:text-red-700 font-semibold text-sm">
                        Remove
                    </button>
                `;
                list.appendChild(li);
            });
        }

        function removeRecipient(index) {
            selectedRecipients.splice(index, 1);
            renderRecipientsList();
        }

        function sendTest() {
            if (selectedRecipients.length === 0 && !document.getElementById('csvFile').files.length) {
                alert('Please select test recipients or upload a CSV file');
                return;
            }

            document.getElementById('testEmailModal').classList.remove('hidden');
        }

        function closeTestModal() {
            document.getElementById('testEmailModal').classList.add('hidden');
        }

        document.getElementById('testEmailForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            setLoading('testBtn');

            const formData = new FormData(document.getElementById('composeForm'));
            formData.append('action', 'test');
            formData.append('test_email', document.getElementById('testEmail').value);
            formData.set('body', CKEDITOR.instances.emailBody.getData());

            try {
                const response = await fetch('api/dispatch.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Test email sent successfully!');
                    closeTestModal();
                    document.getElementById('testEmailForm').reset();
                } else {
                    alert('❌ Error: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                alert('❌ Error sending test email: ' + error.message);
            } finally {
                resetLoading('testBtn');
            }
        });

        function saveDraft() {
            setLoading('draftBtn');

            const formData = new FormData(document.getElementById('composeForm'));
            formData.append('action', 'draft');
            formData.set('body', CKEDITOR.instances.emailBody.getData());

            fetch('api/dispatch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Draft saved successfully!');
                } else {
                    alert('❌ Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => alert('❌ Error: ' + error.message))
            .finally(() => resetLoading('draftBtn'));
        }

        function startDispatch() {
            if (selectedRecipients.length === 0 && !document.getElementById('csvFile').files.length) {
                alert('Please select recipients or upload a CSV file');
                return;
            }

            if (!confirm('🚀 Are you sure you want to start this email dispatch? This action cannot be undone.')) {
                return;
            }

            setLoading('dispatchBtn');

            const formData = new FormData(document.getElementById('composeForm'));
            formData.append('action', 'dispatch');
            formData.set('body', CKEDITOR.instances.emailBody.getData());

            fetch('api/dispatch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Dispatch started successfully!');
                    window.location.href = 'dashboard.php?dispatch_id=' + data.dispatch_id;
                } else {
                    alert('❌ Error: ' + (data.message || 'Unknown error'));
                    resetLoading('dispatchBtn');
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
                resetLoading('dispatchBtn');
            });
        }

        window.onclick = function(event) {
            const modal = document.getElementById('testEmailModal');
            if (event.target == modal) {
                closeTestModal();
            }
        }

        // Initialize
        renderRecipientsList();
    </script>

</body>
</html>

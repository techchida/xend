# Mail Dispatch System

A comprehensive PHP-based email dispatch system with SMTP configuration, test recipient management, WYSIWYG email editor, and detailed campaign analytics.

## Features

### 1. **Admin Management & Security**
- Secure admin login system with password hashing (bcrypt)
- Change password functionality
- Session-based authentication with timeout protection

### 2. **SMTP Configuration Management**
- Add/edit/delete multiple SMTP server configurations
- Support for TLS/SSL encryption
- Per-configuration sender email and name settings
- Store SMTP credentials securely

### 3. **Test Recipients**
- Manage test email recipients (title, first name, last name, email)
- Quick access for sending test emails before dispatch
- Add/edit/delete test recipients

### 4. **Email Composition**
- Rich WYSIWYG editor (CKEditor) for professional email templates
- Set subject, from email, and reply-to address
- Select from configured SMTP servers
- Choose recipients from:
  - Pre-configured test recipients
  - CSV file upload (title, fname, lname, email)

### 5. **Email Campaigns**
- Start email dispatch to multiple recipients
- Save drafts before sending
- Send test emails to verify template
- Real-time campaign status tracking

### 6. **Dashboard & Analytics**
- Overview of all email campaigns
- Individual campaign details with statistics:
  - Total recipients
  - Successfully sent
  - Failed deliveries
  - Emails opened
  - Links clicked
- Recipient-level tracking (status, open/click events with timestamps)

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- OpenSSL support (for SMTP/TLS)

## Installation

### Step 1: Set Up Database Connection

Edit `config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'mail_dispatch_system');
```

### Step 2: Create Database

Run the installation wizard:

1. Visit `http://your-domain/sendr/install.php` in your browser
2. Click "Create Database Tables"
3. Create your admin account
4. Login with your credentials

**OR** manually import the SQL:

```bash
mysql -u root -p mail_dispatch_system < database.sql
```

### Step 3: Configure Directory Permissions

Ensure the `logs` directory is writable:

```bash
mkdir -p logs
chmod 755 logs
```

### Step 4: Set Up Email Queue Processing (Cron Job)

Add this line to your crontab to process emails every 5 minutes:

```bash
*/5 * * * * php /path/to/sendr/process_queue.php >> /path/to/sendr/logs/queue.log 2>&1
```

Or every minute for faster processing:

```bash
* * * * * php /path/to/sendr/process_queue.php >> /path/to/sendr/logs/queue.log 2>&1
```

## Project Structure

```
sendr/
├── index.php              # Redirects to login
├── login.php              # Admin login page
├── logout.php             # Logout handler
├── dashboard.php          # Campaign overview & details
├── compose.php            # Email composition page
├── settings.php           # SMTP, tester, and security settings
├── config.php             # Configuration file
├── Auth.php              # Authentication class
├── Database.php          # Database connection class
├── MailSender.php        # SMTP email sender class
├── process_queue.php     # Email queue processor (cron job)
├── install.php           # Installation wizard
├── database.sql          # Database schema
├── api/
│   ├── smtp.php         # SMTP CRUD operations
│   ├── testers.php      # Test recipients CRUD
│   └── dispatch.php     # Email dispatch operations
└── logs/
    ├── error.log        # PHP errors
    └── queue.log        # Queue processor log
```

## Usage Guide

### 1. First Login

1. Visit `login.php`
2. Enter the admin credentials you created during installation
3. You're now logged in

### 2. Add SMTP Configuration

1. Go to **Settings** → **SMTP Configs**
2. Click **Add New SMTP Config**
3. Fill in:
   - **Configuration Name**: Friendly name (e.g., "Gmail", "SendGrid")
   - **SMTP Host**: smtp.gmail.com, smtp.sendgrid.net, etc.
   - **SMTP Port**: Usually 587 (TLS) or 465 (SSL)
   - **Use TLS**: Enable for most modern SMTP servers
   - **SMTP Username**: Usually your email address or API key
   - **SMTP Password**: Your password or API secret
   - **From Email**: The sender email address
   - **From Name**: The sender display name (optional)
4. Click **Save SMTP Config**

#### Gmail Setup Example:
```
Host: smtp.gmail.com
Port: 587
Use TLS: Yes
Username: your-email@gmail.com
Password: your-app-password (not your Gmail password)
```

**Note:** Gmail requires an App Password. Enable 2FA and create one at https://myaccount.google.com/apppasswords

### 3. Add Test Recipients

1. Go to **Settings** → **Test Recipients**
2. Click **Add Test Recipient**
3. Enter details (Title is optional)
4. Click **Save Tester**

### 4. Compose Email Campaign

1. Go to **Compose**
2. Fill in:
   - **Subject**: Email subject line
   - **SMTP Config**: Select your configured SMTP
   - **From Email**: Sender email address
   - **Reply To**: Optional reply-to address
   - **Email Body**: Use the rich editor to compose
3. Select recipients:
   - Check test recipients, OR
   - Upload CSV file with columns: title, fname, lname, email
4. Click buttons:
   - **Send Test Email**: Test with a single email first
   - **Save as Draft**: Save without sending
   - **Start Dispatch**: Send to all recipients

### 5. Monitor Campaigns

1. Go to **Dashboard**
2. See list of all campaigns
3. Click **View Details** on any campaign to see:
   - Detailed statistics
   - List of recipients with delivery status
   - Open and click tracking

## CSV Upload Format

When uploading a CSV file with recipients, use this format:

```csv
title,fname,lname,email
Mr.,John,Doe,john@example.com
Ms.,Jane,Smith,jane@example.com
Dr.,Robert,Johnson,robert@example.com
,Michael,Williams,michael@example.com
```

**Note:** Title is optional, leave blank or omit if not needed.

## API Endpoints

### SMTP Management
- **POST** `/api/smtp.php` - Create/Update/Delete SMTP configs
  ```json
  {
    "action": "create|update|delete",
    "name": "Config Name",
    "host": "smtp.example.com",
    "port": 587,
    "username": "user@example.com",
    "password": "password",
    "from_email": "sender@example.com",
    "from_name": "Sender Name",
    "use_tls": true
  }
  ```

### Test Recipients
- **POST** `/api/testers.php` - Create/Update/Delete test recipients
  ```json
  {
    "action": "create|update|delete",
    "title": "Mr.",
    "fname": "John",
    "lname": "Doe",
    "email": "john@example.com"
  }
  ```

### Email Dispatch
- **POST** `/api/dispatch.php` - Save draft, test send, or start dispatch
  ```
  Multipart form data with:
  - action: draft|test|dispatch
  - subject, from_email, reply_to, body
  - smtp_id, test_recipients (array), csv_file
  ```

## Database Schema

### admins
```sql
id, username, password (hashed), created_at, updated_at
```

### smtp_configs
```sql
id, admin_id, name, host, port, username, password,
from_email, from_name, use_tls, created_at, updated_at
```

### testers
```sql
id, admin_id, title, fname, lname, email, created_at, updated_at
```

### dispatches
```sql
id, admin_id, smtp_config_id, subject, from_email, reply_to, body,
status (draft/scheduled/sending/completed/failed), total_recipients,
sent_count, failed_count, opened_count, clicked_count,
scheduled_at, started_at, completed_at, created_at, updated_at
```

### dispatch_recipients
```sql
id, dispatch_id, title, fname, lname, email,
status (pending/sent/failed/bounced),
opened, opened_at, clicked, clicked_at,
error_message, created_at, updated_at
```

### email_queue
```sql
id, dispatch_id, recipient_id, attempt_count, max_attempts,
next_attempt, status (pending/sent/failed), error_message,
created_at, updated_at
```

## Queue Processing

The `process_queue.php` script:

1. Fetches pending emails from the queue (up to 10 at a time)
2. Connects to the configured SMTP server
3. Sends each email with retry logic:
   - Max 3 attempts per email
   - 5-minute delay between retries
4. Updates dispatch statistics
5. Marks dispatch as completed when all emails are processed

**Run via cron job** every minute for continuous processing.

## Security Considerations

1. **Password Security**: All admin passwords are hashed with bcrypt
2. **SMTP Credentials**: Encrypted storage recommended (consider encrypting at application level)
3. **Session Management**: Automatic timeout after 1 hour of inactivity
4. **Input Validation**: All user inputs are validated and sanitized
5. **CSRF Protection**: Recommended to add token validation
6. **File Permissions**: Keep logs directory accessible only to web server
7. **Database Access**: Use strong database passwords and limit IP access

## Troubleshooting

### "SMTP connection failed"
- Verify host and port are correct
- Check firewall/network access to SMTP server
- Enable TLS if required by your provider
- Check username/password credentials

### "Database connection failed"
- Verify database credentials in config.php
- Ensure database server is running
- Check database name exists

### Emails not sending
- Verify cron job is running: `ps aux | grep process_queue.php`
- Check logs in `logs/` directory
- Ensure SMTP configuration is correct
- Verify recipient email addresses are valid

### Session timeout issues
- Adjust `SESSION_TIMEOUT` in config.php (in seconds)
- Check PHP session configuration

## Email Tracking

The system tracks:
- **Delivery**: Sent/Failed status
- **Opens**: When recipient opens the email
- **Clicks**: When recipient clicks a link

**Note:** Tracking requires pixel/link injection. Implement tracking in the email body template.

## Performance Tips

1. **Process Queue**: Set cron job to run every 1-5 minutes for batch processing
2. **Database**: Index dispatch and recipient tables for faster queries
3. **Limits**: Adjust batch size in `process_queue.php` for large sends

## Support & Issues

For issues or feature requests, contact your system administrator or check the logs in the `logs/` directory for detailed error information.

## License

This Mail Dispatch System is provided as-is for your use.

## Version

Version 1.0 - January 2026

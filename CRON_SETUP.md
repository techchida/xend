# Setting Up Cron Job for Email Queue Processing

The Mail Dispatch System uses a queue-based approach for sending emails. The `process_queue.php` script must be executed periodically to process and send queued emails.

## Setting Up Cron Jobs

### Linux/Unix Systems

1. **Open your crontab editor:**
   ```bash
   crontab -e
   ```

2. **Add one of these lines** (choose frequency based on your needs):

   **Every minute (fastest processing):**
   ```bash
   * * * * * php /var/www/html/sendr/process_queue.php >> /var/www/html/sendr/logs/queue.log 2>&1
   ```

   **Every 5 minutes (recommended):**
   ```bash
   */5 * * * * php /var/www/html/sendr/process_queue.php >> /var/www/html/sendr/logs/queue.log 2>&1
   ```

   **Every 10 minutes:**
   ```bash
   */10 * * * * php /var/www/html/sendr/process_queue.php >> /var/www/html/sendr/logs/queue.log 2>&1
   ```

3. **Save and exit** (in nano: Ctrl+X, then Y, then Enter)

4. **Verify the cron job was added:**
   ```bash
   crontab -l
   ```

### Shared Hosting (cPanel/Plesk)

1. **Login to your hosting control panel**

2. **Find "Cron Jobs" or "Scheduled Tasks"**

3. **Create a new cron job:**
   - **Command:** `php /home/yourusername/public_html/sendr/process_queue.php >> /home/yourusername/public_html/sendr/logs/queue.log 2>&1`
   - **Common Setting:** Every 5 minutes
   - **Enable:** Check the enabled box

4. **Save the cron job**

### Windows Server (Task Scheduler)

1. **Open Task Scheduler**
   - Press `Win + R` → Type `taskschd.msc` → Enter

2. **Create Basic Task**
   - Right-click → New Folder → Name: "Mail Dispatch"
   - Right-click → Create Basic Task

3. **Fill in details:**
   - **Name:** Email Queue Processor
   - **Trigger:** Daily (or hourly) → Repeat every 5 minutes for duration of 1 day
   - **Action:** Start a program
     - Program: `C:\php\php.exe`
     - Arguments: `C:\inetpub\wwwroot\sendr\process_queue.php`

4. **Click Finish**

## Verifying Cron Job Setup

### Check if cron job is running

**Linux/Unix:**
```bash
# Check system cron logs (varies by system)
grep CRON /var/log/syslog          # Ubuntu/Debian
grep CRON /var/log/messages        # CentOS/RHEL
tail -f /var/log/cron              # Some systems

# Or check if process_queue.php appears in running processes
ps aux | grep process_queue.php
```

**Check the queue log file:**
```bash
tail -f /var/www/html/sendr/logs/queue.log
```

### Monitor email queue

Check the email queue in your database:

```sql
-- View pending emails in queue
SELECT COUNT(*) as pending_emails
FROM email_queue
WHERE status = 'pending';

-- View dispatch status
SELECT id, subject, status, sent_count, failed_count
FROM dispatches
ORDER BY created_at DESC;

-- View individual email queue entries
SELECT eq.*, d.subject
FROM email_queue eq
JOIN dispatches d ON eq.dispatch_id = d.id
WHERE eq.status = 'pending'
LIMIT 10;
```

## Troubleshooting Cron Jobs

### Cron job not running

1. **Check if cron service is running:**
   ```bash
   service cron status      # Ubuntu/Debian
   service crond status     # CentOS/RHEL
   ```

2. **Check cron permissions:**
   ```bash
   # If user is in /etc/cron.deny, remove them
   sudo nano /etc/cron.deny

   # If /etc/cron.allow exists, add user
   sudo nano /etc/cron.allow
   ```

3. **Manually test the script:**
   ```bash
   php /path/to/sendr/process_queue.php
   ```

4. **Check PHP path:**
   ```bash
   which php
   # Use the full path returned
   /usr/bin/php /path/to/sendr/process_queue.php
   ```

### Emails still not being sent

1. **Check the queue log file:**
   ```bash
   tail -100 /path/to/sendr/logs/queue.log
   ```

2. **Check PHP error log:**
   ```bash
   tail -100 /path/to/sendr/logs/error.log
   ```

3. **Verify SMTP configuration:**
   - Login to the web interface
   - Go to Settings → SMTP Configs
   - Test connection by sending a test email

4. **Check database connection:**
   ```bash
   # Run the script manually with verbose output
   php -r "require 'config.php'; require 'Database.php'; \$db = Database::getInstance(); echo 'Connected!'"
   ```

### High CPU usage from cron

- If running every minute causes high CPU, increase to every 5 minutes
- Optimize database queries (add indexes)
- Process queue in the logs directory to monitor

## Best Practices

1. **Run frequently but not too often:** Every 5 minutes is usually ideal
2. **Monitor the queue log** for errors
3. **Limit batch size** to prevent memory issues (currently 10 emails per run)
4. **Set up log rotation** to prevent logs from growing too large
5. **Test before going live** - Run the script manually first

## Log Rotation (Linux/Unix)

Prevent log files from growing too large by setting up log rotation:

1. **Create `/etc/logrotate.d/mail-dispatch`:**
   ```bash
   sudo nano /etc/logrotate.d/mail-dispatch
   ```

2. **Add configuration:**
   ```
   /var/www/html/sendr/logs/*.log {
       daily
       rotate 14
       compress
       delaycompress
       notifempty
       create 0640 www-data www-data
   }
   ```

3. **Save and exit**

## Quick Reference

| Frequency | Cron Expression |
|-----------|-----------------|
| Every minute | `* * * * *` |
| Every 5 minutes | `*/5 * * * *` |
| Every 10 minutes | `*/10 * * * *` |
| Every 30 minutes | `*/30 * * * *` |
| Every hour | `0 * * * *` |
| Every day at 2 AM | `0 2 * * *` |

## Getting Help

If cron jobs still aren't working:

1. Contact your hosting provider for system-level support
2. Check PHP error logs in the control panel
3. Verify file permissions on `process_queue.php`
4. Ensure the PHP-CLI version matches your web server version

## Testing the Queue Processor

To test if the queue processor works correctly:

```bash
# Run manually and capture output
php /path/to/sendr/process_queue.php

# Expected output:
# Processing X emails from queue...
# ✓ Sent to email@example.com
# ✓ Sent to another@example.com
# Done!
```

If you see errors, check the logs for the specific issue.

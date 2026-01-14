# Quick Start Guide - Mail Dispatch System

Get your Mail Dispatch System up and running in 5 minutes!

## Prerequisites

- PHP 7.4+ installed
- MySQL 5.7+ running
- Web server (Apache/Nginx)
- Database credentials ready

## 5-Minute Setup

### Step 1: Configure Database (1 minute)

Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'mail_dispatch_system');
```

Create the database:
```bash
mysql -u your_db_user -p -e "CREATE DATABASE mail_dispatch_system;"
```

### Step 2: Run Installation (2 minutes)

1. Open your browser to: `http://your-domain/sendr/install.php`
2. Click "Create Database Tables"
3. Enter admin username and password
4. Click "Create Admin Account"
5. You're done! You'll see "Installation Complete ✓"

### Step 3: First Login (1 minute)

1. Go to: `http://your-domain/sendr/login.php`
2. Enter your admin credentials
3. You're now in the dashboard

### Step 4: Set Up Email (1 minute)

1. Click **Settings** in the top menu
2. Go to **SMTP Configs** tab
3. Click **Add New SMTP Config**
4. Enter your SMTP details:

#### Gmail Example:
```
Name: Gmail
Host: smtp.gmail.com
Port: 587
Use TLS: Yes
Username: your-email@gmail.com
Password: your-app-password
From Email: your-email@gmail.com
From Name: Your Name
```

**Note:** Gmail requires an App Password. Get it at https://myaccount.google.com/apppasswords

#### SendGrid Example:
```
Name: SendGrid
Host: smtp.sendgrid.net
Port: 587
Use TLS: Yes
Username: apikey
Password: SG.your-api-key-here
From Email: noreply@yourdomain.com
From Name: Your Company
```

5. Click **Save SMTP Config**

## Send Your First Email

### Step 1: Add a Test Recipient

1. Go to **Settings** → **Test Recipients**
2. Click **Add Test Recipient**
3. Fill in details (only First Name, Last Name, Email required)
4. Click **Save Tester**

### Step 2: Compose Email

1. Click **Compose** in the top menu
2. Fill in:
   - **Subject:** "Welcome to Mail Dispatch"
   - **SMTP Config:** Select your Gmail config
   - **From Email:** your-email@gmail.com
   - **Email Body:** Write something nice! Use the editor

3. **Select Recipients:**
   - Check your test recipient

4. Click **Send Test Email**
   - Enter your email address
   - Click **Send Test**

5. Check your inbox! ✓

## Next Steps

### Set Up Automated Sending

To actually send campaigns, set up the cron job:

```bash
# Add to crontab
*/5 * * * * php /path/to/sendr/process_queue.php >> /path/to/sendr/logs/queue.log 2>&1
```

See [CRON_SETUP.md](CRON_SETUP.md) for detailed instructions.

### Create Your First Campaign

1. Go to **Compose**
2. Fill in email details
3. Add recipients:
   - Check test recipients, OR
   - Upload CSV file
4. Click **Send Test** to verify
5. Click **Start Dispatch** to send to all!

## Common Tasks

### Add More Test Recipients

Settings → Test Recipients → Add Test Recipient

### Upload CSV of Recipients

Format:
```csv
title,fname,lname,email
Mr.,John,Doe,john@example.com
Ms.,Jane,Smith,jane@example.com
```

Then upload in Compose page

### Check Campaign Status

Dashboard → View Details → See statistics

### Change Admin Password

Settings → Security → Change Password

## SMTP Providers

### Free/Affordable Options

| Provider | Host | Port | Features |
|----------|------|------|----------|
| Gmail | smtp.gmail.com | 587 | Free, needs App Password |
| SendGrid | smtp.sendgrid.net | 587 | Free tier available |
| Mailgun | smtp.mailgun.org | 587 | Free tier, good API |
| AWS SES | email-smtp.region.amazonaws.com | 587 | Very cheap at scale |
| Brevo (Sendinblue) | smtp-relay.brevo.com | 587 | Good deliverability |

## Troubleshooting

### "Can't connect to database"
- Check credentials in `config.php`
- Verify MySQL is running
- Try creating database manually

### "Emails not sending"
- Go to Settings → SMTP Configs
- Test with a simple email first
- Check SMTP credentials are correct
- Set up cron job to process queue

### "Can't login"
- Make sure you created admin account during install
- Check caps lock
- Try resetting by deleting admin record and re-running install

### "SMTP connection failed"
- Verify hostname is correct
- Check port (usually 587 or 465)
- Try enabling/disabling TLS
- Check your firewall allows SMTP traffic

## Tips & Tricks

1. **Always test first** - Send test email before big campaign
2. **Use templates** - Save frequently used templates
3. **Monitor delivery** - Check Dashboard for bounce/error rates
4. **Clean lists** - Remove invalid emails to improve deliverability
5. **Set up cron** - Without it, emails won't send automatically
6. **Check logs** - If something breaks, look in `logs/error.log`

## File Structure

```
sendr/
├── login.php          ← Start here
├── dashboard.php      ← View campaigns
├── compose.php        ← Send emails
├── settings.php       ← Configure SMTP & testers
├── process_queue.php  ← Run via cron (automated)
├── install.php        ← Already used
├── config.php         ← Edit database credentials
└── logs/              ← Check for errors
    ├── error.log
    └── queue.log
```

## Security Checklist

- ✓ Changed admin password from installation
- ✓ Stored SMTP passwords securely
- ✓ Disabled directory listing (check .htaccess)
- ✓ Set proper file permissions
- ✓ Enabled HTTPS if possible
- ✓ Backed up database regularly

## Performance Tips

1. **Run cron every 5 minutes** - Faster queue processing
2. **Limit CSV to 10,000 recipients** - Prevent timeout
3. **Use separate SMTP for testing** - Different from production
4. **Monitor database** - Optimize tables after heavy use
5. **Clean old logs** - Prevent disk space issues

## Getting Help

1. Check [README.md](README.md) for full documentation
2. Review [CRON_SETUP.md](CRON_SETUP.md) for cron issues
3. Check `logs/error.log` for error details
4. Verify SMTP settings with provider documentation

## What's Next?

- Learn about [advanced features](README.md)
- Set up [automated cron jobs](CRON_SETUP.md)
- Configure [email tracking](README.md#email-tracking)
- Explore [API endpoints](README.md#api-endpoints)

Good luck with your email campaigns! 🚀

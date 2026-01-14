# File Structure & Description

## Project Overview

This is a complete PHP-based Mail Dispatch System with the following main components:

1. **Authentication System** - Admin login and session management
2. **SMTP Configuration** - Multiple email server setup
3. **Recipient Management** - Test recipients and bulk CSV upload
4. **Email Composition** - WYSIWYG editor for templates
5. **Campaign Management** - Queue-based email dispatching
6. **Analytics Dashboard** - Campaign tracking and statistics

---

## Core Files

### `config.php`
- **Purpose:** Application configuration file
- **Contains:**
  - Database credentials
  - Session timeout settings
  - Upload limits
  - Default SMTP settings
  - Security configurations
- **Edit this first:** Set your database credentials here

### `index.php`
- **Purpose:** Entry point
- **Behavior:** Redirects to login.php
- **Usage:** Access via `http://your-domain/sendr/`

### `.htaccess`
- **Purpose:** Apache server configuration
- **Contains:**
  - URL rewriting rules
  - Security headers
  - File access restrictions
  - Compression settings
- **Required for:** Clean URLs and security

---

## Authentication System

### `Auth.php`
- **Purpose:** Authentication logic class
- **Methods:**
  - `login($username, $password)` - Admin login
  - `logout()` - End session
  - `isLoggedIn()` - Check session status
  - `getAdminId()` - Get current admin ID
  - `changePassword($adminId, $oldPassword, $newPassword)` - Change admin password
  - `createAdmin($username, $password)` - Create new admin (install only)
- **Used by:** All pages that require authentication

### `login.php`
- **Purpose:** Admin login page
- **Features:**
  - Username/password input
  - Session creation
  - Error handling
  - Beautiful gradient UI
- **Access:** Before authentication
- **Redirect:** To dashboard if already logged in

### `logout.php`
- **Purpose:** Logout handler
- **Behavior:** Destroys session and redirects to login
- **Called from:** All authenticated pages via logout button

---

## Database Layer

### `Database.php`
- **Purpose:** Database connection and query execution class
- **Methods:**
  - `getInstance()` - Singleton pattern
  - `getConnection()` - Get MySQLi connection
  - `query($sql, $params)` - Execute prepared statement
  - `insert($table, $data)` - Insert records
  - `getLastInsertId()` - Get last insert ID
- **Features:**
  - Prepared statements (SQL injection prevention)
  - Connection pooling
  - Error logging
- **Used by:** All classes accessing database

### `database.sql`
- **Purpose:** Database schema definition
- **Contains:** CREATE TABLE statements for:
  - admins
  - smtp_configs
  - testers
  - dispatches
  - dispatch_recipients
  - email_queue
- **Executed during:** Installation process

---

## Email System

### `MailSender.php`
- **Purpose:** SMTP email sending class
- **Methods:**
  - `__construct()` - Initialize with SMTP config
  - `send($toEmail, $toName, $subject, $body, $replyTo)` - Send email via SMTP
  - `connect()` - Establish SMTP connection
  - `sendCommand()` - Send SMTP command
  - `getResponse()` - Read SMTP response
  - `sendSimple()` - Alternative PHP mail() method
- **Features:**
  - Direct SMTP communication
  - TLS/SSL support
  - SMTP authentication
  - HTML email support
  - Custom headers
- **Used by:** compose.php and process_queue.php

### `process_queue.php`
- **Purpose:** Email queue processor (cron job)
- **Behavior:**
  - Fetches pending emails from queue
  - Connects to SMTP servers
  - Sends emails with retry logic
  - Updates dispatch statistics
  - Marks dispatches as completed
- **Execution:** Via cron job every 5 minutes
- **Key features:**
  - Automatic retry (max 3 attempts)
  - 5-minute delay between retries
  - Batch processing (10 at a time)
  - Error logging
  - Dispatch status tracking

---

## User Interface Pages

### `dashboard.php`
- **Purpose:** Main dashboard and analytics
- **Views:**
  1. **Overview Mode** - List all campaigns
     - Displays recent campaigns in table
     - Shows subject, status, statistics
     - Quick links to detailed view
  2. **Details Mode** - Individual dispatch analysis
     - Campaign details (subject, sender, dates)
     - Statistics cards (sent, failed, opened, clicked)
     - Full recipient list with delivery status
- **Features:**
  - Real-time status updates
  - Recipient tracking (opened/clicked)
  - Error messages display
  - Clickable rows for details
- **Access:** After login, main navigation

### `compose.php`
- **Purpose:** Email campaign composition
- **Features:**
  1. **Email Details Form:**
     - Subject line
     - SMTP configuration selection
     - From email and reply-to address
  2. **WYSIWYG Editor (CKEditor):**
     - Rich text formatting
     - Font/color options
     - Bullet points and numbering
     - Image and table insertion
     - HTML source view
  3. **Recipient Selection:**
     - Checkbox list of test recipients
     - CSV file upload
     - Real-time count of selected
  4. **Action Buttons:**
     - Send Test - Test email to single address
     - Save as Draft - Save without sending
     - Start Dispatch - Send to all recipients
- **Validation:**
  - Required fields checking
  - Email format validation
  - At least one recipient required
  - SMTP configuration validation
- **Access:** After login, via navigation

### `settings.php`
- **Purpose:** System configuration page
- **Tabs:**
  1. **SMTP Configurations**
     - List all configured SMTP servers
     - Add new SMTP config modal
     - Edit existing SMTP
     - Delete SMTP config
     - Fields: name, host, port, username, password, TLS, from email/name
  2. **Test Recipients**
     - List all test email recipients
     - Add new tester modal
     - Edit existing tester
     - Delete tester
     - Fields: title (optional), fname, lname, email
  3. **Security**
     - Change admin password
     - Current password verification
     - New password confirmation
- **Features:**
  - Modal dialogs for add/edit
  - Delete confirmation prompts
  - Real-time validation
  - AJAX form submission
- **Access:** After login, via navigation

---

## API Endpoints

### `api/smtp.php`
- **Purpose:** SMTP configuration API
- **Methods:** POST only
- **Actions:**
  - `create` - Add new SMTP config
    - Validates all required fields
    - Checks for duplicate names
    - Inserts into database
  - `update` - Modify existing SMTP
    - Verifies ownership (admin_id)
    - Updates configuration
  - `delete` - Remove SMTP config
    - Verifies ownership
    - Deletes from database
    - Prevents deletion of used configs (optional)
- **Response:** JSON with success/error message
- **Authentication:** Requires logged-in session
- **Error Handling:** Try-catch with meaningful messages

### `api/testers.php`
- **Purpose:** Test recipients API
- **Methods:** POST only
- **Actions:**
  - `create` - Add new test recipient
    - Validates email format
    - Checks required fields
    - Prevents duplicate emails (optional)
  - `update` - Modify test recipient
    - Verifies ownership
    - Validates email format
    - Updates record
  - `delete` - Remove test recipient
    - Verifies ownership
    - Deletes from database
- **Response:** JSON with success/error message
- **Authentication:** Requires logged-in session

### `api/dispatch.php`
- **Purpose:** Email campaign operations API
- **Methods:** POST with multipart form data
- **Actions:**
  - `draft` - Save campaign as draft
    - Saves email template and settings
    - No recipients added
    - Can be edited later
    - Validates SMTP configuration
  - `test` - Send single test email
    - Validates test email address
    - Sends via configured SMTP
    - Returns success/failure result
    - Useful for template testing
  - `dispatch` - Start campaign
    - Creates dispatch record
    - Adds recipients from:
      - Selected test recipients
      - Uploaded CSV file
    - Creates email queue entries
    - Updates dispatch status to "sending"
    - Returns dispatch ID
- **Parameters:**
  - subject, from_email, reply_to, body
  - smtp_id, test_recipients (array)
  - csv_file (multipart file)
- **Response:** JSON with success, message, dispatch_id
- **Authentication:** Requires logged-in session

---

## Installation & Setup

### `install.php`
- **Purpose:** Interactive installation wizard
- **Steps:**
  1. **Step 1: Database Setup**
     - Check database connection
     - Create all tables from database.sql
     - Progress tracking
  2. **Step 2: Admin Account**
     - Create first admin user
     - Password validation (min 8 chars)
     - Confirm password matching
  3. **Step 3: Completion**
     - Success message
     - Next steps information
     - Link to login page
- **Features:**
  - Progress bar
  - Error handling and messages
  - Database verification
  - Step-by-step guidance
- **Access:** First-time setup only
- **Security:** Password hashing with bcrypt

---

## Documentation

### `README.md`
- **Comprehensive guide** covering:
  - Features overview
  - Requirements and installation
  - Project structure
  - Usage guide with examples
  - CSV format specifications
  - API endpoint documentation
  - Database schema details
  - Queue processor explanation
  - Performance tips
  - Troubleshooting guide
- **Audience:** Developers and administrators

### `QUICKSTART.md`
- **5-minute setup guide** with:
  - Prerequisites checklist
  - Step-by-step installation
  - First email sending
  - Common SMTP providers
  - Quick troubleshooting
  - Tips and tricks
  - Next steps
- **Audience:** New users

### `CRON_SETUP.md`
- **Detailed cron job guide** for:
  - Linux/Unix crontab setup
  - Shared hosting (cPanel/Plesk)
  - Windows Task Scheduler
  - Verification methods
  - Troubleshooting
  - Log rotation
  - Best practices
- **Audience:** System administrators

### `FILES.md` (this file)
- **Complete file reference** with:
  - Purpose of each file
  - Methods and features
  - Dependencies and relationships
  - When/how each file is used

---

## Key Dependencies

### External Libraries
- **CKEditor 4.21.0** - WYSIWYG editor (loaded from CDN)
  - URL: https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js
  - Used in: compose.php
  - Provides: HTML email editing interface

### PHP Built-in Functions
- MySQLi - Database connection
- Session functions - Authentication
- Mail functions - Email sending
- File functions - CSV upload handling
- Crypt functions - Password hashing

---

## Directory Structure

```
sendr/
│
├── Core System Files
│   ├── index.php                 # Entry point
│   ├── config.php                # Configuration
│   ├── Auth.php                  # Authentication class
│   ├── Database.php              # Database class
│   ├── MailSender.php            # Email sending class
│   └── .htaccess                 # Server configuration
│
├── Pages (User Interface)
│   ├── login.php                 # Login page
│   ├── dashboard.php             # Dashboard & analytics
│   ├── compose.php               # Email composition
│   ├── settings.php              # Configuration page
│   ├── logout.php                # Logout handler
│   └── install.php               # Installation wizard
│
├── API Endpoints
│   └── api/
│       ├── smtp.php              # SMTP API
│       ├── testers.php           # Testers API
│       └── dispatch.php          # Dispatch API
│
├── Queue Processing
│   └── process_queue.php         # Email queue processor
│
├── Database
│   └── database.sql              # Schema definition
│
├── Logs (created at runtime)
│   └── logs/
│       ├── error.log             # PHP errors
│       └── queue.log             # Queue processor log
│
└── Documentation
    ├── README.md                 # Full documentation
    ├── QUICKSTART.md             # Quick start guide
    ├── CRON_SETUP.md             # Cron job guide
    └── FILES.md                  # This file
```

---

## Workflow Summary

### User Flow
1. **Installation**: Visit install.php → Create admin → DB setup
2. **Login**: login.php → Enter credentials → Create session
3. **Settings**: Configure SMTP → Add test recipients
4. **Compose**: Create email → Add recipients → Send/Draft/Test
5. **Dashboard**: Monitor campaigns → View statistics → Track delivery

### Email Flow
1. **Compose Page**: User creates email and selects recipients
2. **Dispatch API**: Creates dispatch record and queue entries
3. **Queue Table**: Stores each recipient email to send
4. **Process Queue**: Cron job reads queue and sends via SMTP
5. **Recipient Update**: Updates delivery status and statistics
6. **Dashboard**: Shows real-time campaign progress

---

## File Dependencies

```
login.php
  └── Auth.php
      └── Database.php

dashboard.php
  └── Auth.php
      └── Database.php

compose.php
  ├── Auth.php
  │   └── Database.php
  └── CKEditor (CDN)

settings.php
  ├── Auth.php
  │   └── Database.php
  └── api/smtp.php & api/testers.php

api/dispatch.php
  ├── Auth.php
  │   └── Database.php
  └── MailSender.php

process_queue.php
  ├── Database.php
  └── MailSender.php

install.php
  ├── Database.php
  ├── Auth.php
  └── database.sql
```

---

## Notes

- All files use prepared statements to prevent SQL injection
- AJAX is used for non-blocking operations
- CSS is inline in HTML (no external stylesheets)
- Mobile responsive design included
- Error logging for debugging
- Session-based authentication
- SMTP credentials stored in database

---

See [README.md](README.md) for more detailed documentation.

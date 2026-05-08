# Penalis Emailer

A WordPress plugin that automatically notifies post authors via HTML email when their posts are published, with a comprehensive administrative interface for manual email management including background queue processing and draft management.

**Note:** This plugin was originally developed for internal use at [Penalis](https://penalis.com) to manage post author notifications on our website. However, we've made it publicly available so others can use it in their projects if they find it useful.

## Features

### Core Functionality
- **Automatic Notifications** - Sends HTML email notifications to post authors when posts are published
- **Manual Email Interface** - Admin interface for sending emails to selected users with markdown support
- **HTML Email Templates** - Professional, responsive templates with dynamic placeholder replacement
- **Duplicate Prevention** - Prevents sending multiple emails for the same post publication
- **Email History & Management** - Comprehensive email tracking with separate tabs for manual and automatic emails, bulk delete operations, and clear all functionality
- **Markdown Support** - Write emails in markdown with automatic HTML conversion

### Queue & Draft Features
- **Background Email Queue** - Emails are sent asynchronously via WP-Cron, preventing PHP timeouts on large recipient lists
- **Email Draft Management** - Save, load, and edit drafts with auto-save every 60 seconds
- **Queue Monitor** - Admin page for job monitoring, configuration, and cancellation
- **Progress Tracking** - Real-time progress banner with AJAX polling after queuing emails
- **Rate Limiting** - Configurable delay between emails to respect SMTP provider limits
- **Retry with Backoff** - Automatic retry on failure: 5 min → 15 min → permanently failed
- **Team Transparency** - Drafts record who created, last edited, and sent each email

### Technical Features
- **Service Container** - Automatic dependency injection and resolution
- **Repository Pattern** - Clean data access abstraction layer
- **Custom Database Tables** - Dedicated tables for email logs, queue, and drafts
- **Validation System** - Flexible, reusable validation with custom rules
- **Exception Handling** - Comprehensive error handling with custom exception classes
- **Interface-Based Design** - 6 interfaces for loose coupling and testability
- **SMTP Compatible** - Works seamlessly with any SMTP plugin

## Requirements

- WordPress 6.6 or higher
- PHP 7.4 or higher
- No external dependencies required

## Installation

1. Download the plugin files
2. Upload the `penalis-emailer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. (Optional) Configure SMTP settings for reliable email delivery
5. (Optional) Adjust queue settings from **Penalis Email → Queue Monitor**

## Usage

### Automatic Email Notifications

Once activated, the plugin automatically sends email notifications to post authors when their posts are published.

**Triggers:**
- Post status changes to "publish"
- Post type is "post" (not pages or custom post types)
- Post is not a revision
- Email has not been sent previously

**Email includes:**
- Personalized greeting with author's name
- Post title and link
- Professional HTML template

### Manual Email Sending

Navigate to **Penalis Email** → **Compose** in WordPress admin:

1. Enter email subject and from name
2. Write message in markdown or HTML
3. Select recipients from authors and contributors
4. Preview before sending
5. Click **Send Email**

Starting from v2.0.0, emails are sent asynchronously in the background via WP-Cron. After clicking Send, a progress banner appears showing real-time delivery status. This prevents PHP timeouts when sending to large recipient lists.

### Draft Management

Navigate to **Penalis Email** → **Drafts**:

- Save drafts manually from the Compose page or let auto-save handle it (every 60 seconds)
- Load a draft from the dropdown on the Compose page
- View all drafts in the Drafts management table
- Bulk delete drafts
- Each draft shows who created it, who last edited it, and (after sending) who sent it

### Queue Monitor

Navigate to **Penalis Email** → **Queue Monitor**:

- **Active Jobs** — progress bar, sent/pending/failed counts per job, cancel button
- **Recently Completed** — last 10 completed jobs with timestamps
- **Queue Settings** — configure batch size, throttle delay, interval, and max retries
- **Queue Statistics** — breakdown of all queue items by status
- **Throughput Estimate** — auto-calculated emails/minute based on current settings
- **Cron Health** — warning if WP-Cron is disabled or no run is scheduled

### Template Customization

Navigate to **Penalis Email** → **Template Settings**:

1. Edit the automatic email template
2. Use placeholders: `{AUTHOR_NAME}`, `{POST_TITLE}`, `{POST_URL}`, `{BUTTON_CTA}`
3. Write in markdown for easy formatting
4. Preview changes before saving
5. Reset to default at any time

### Email History

Navigate to **Penalis Email** → **Email History**:

**Features:**
- **Separate Tabs** - View manual and automatic emails in dedicated tabs
- **Comprehensive Tracking** - All sent emails logged with timestamp, recipients, and status
- **Bulk Delete** - Select multiple emails and delete them at once
- **Clear All History** - Remove all emails from a specific tab (manual or automatic)
- **WordPress-Style Interface** - Familiar bulk actions interface

**Manual Email Tab:**
- Subject, Recipients count, Sent By, Sent At, Status

**Automatic Email Tab:**
- Post title with link, Recipient name, Sent At, Status

## Configuration

Key constants in `includes/class-config.php` that can be adjusted by developers:

| Constant | Default | Description |
|----------|---------|-------------|
| `RECIPIENTS_INITIAL_LOAD` | `30` | Users rendered on initial Compose page load |
| `DEFAULT_QUEUE_BATCH_SIZE` | `30` | Emails per cron batch |
| `DEFAULT_QUEUE_INTERVAL` | `60` | Seconds between batches |
| `DEFAULT_QUEUE_MAX_ATTEMPTS` | `3` | Max retries before permanently failed |
| `DEFAULT_QUEUE_THROTTLE_DELAY` | `500000` | Microseconds between emails (500 ms) |

`QUEUE_BATCH_SIZE`, `QUEUE_INTERVAL`, `QUEUE_MAX_ATTEMPTS`, and `QUEUE_THROTTLE_DELAY` can also be changed from the Queue Monitor page in the admin UI without editing code.

## Architecture

### Clean Architecture Principles

```
┌─────────────────────────────────────────┐
│         Presentation Layer              │
│  (Admin Pages, Views, Assets)           │
├─────────────────────────────────────────┤
│         Application Layer               │
│  (Controllers, AJAX, Validators)        │
├─────────────────────────────────────────┤
│          Business Layer                 │
│  (Email Sender, Queue Processor,        │
│   Template, Parser)                     │
├─────────────────────────────────────────┤
│           Data Layer                    │
│  (Repositories, Logger,                 │
│   Custom DB Tables)                     │
├─────────────────────────────────────────┤
│       Infrastructure Layer              │
│  (WordPress APIs, Service Container,    │
│   WP-Cron)                              │
└─────────────────────────────────────────┘
```

### File Structure

```
penalis-emailer/
├── penalis-emailer.php                      # Plugin entry point, bootstrap, hooks
├── includes/
│   ├── class-config.php                     # Centralized configuration constants
│   ├── class-database.php                   # Custom table schema, install, migration
│   ├── class-service-container.php          # Dependency injection container
│   ├── class-email-sender.php               # Email sending orchestrator (enqueues jobs)
│   ├── class-email-queue-processor.php      # WP-Cron batch processor with retry logic
│   ├── class-email-template.php             # Template rendering with placeholder support
│   ├── class-email-logger.php               # Email logging facade
│   ├── class-markdown-parser.php            # Markdown to HTML conversion
│   ├── admin/
│   │   ├── class-admin-interface.php        # Main admin coordinator, menu registration
│   │   ├── class-admin-page.php             # Base page class (assets, nonces, notices)
│   │   ├── class-compose-page.php           # Compose email page with infinite scroll
│   │   ├── class-draft-page.php             # Draft management page
│   │   ├── class-history-page.php           # Email history page
│   │   ├── class-settings-page.php          # Template settings page
│   │   ├── class-queue-monitor-page.php     # Queue monitor page
│   │   ├── class-ajax-handler.php           # All AJAX endpoints
│   │   └── views/                           # HTML view partials
│   │       ├── email-content-card.php
│   │       ├── email-details-card.php
│   │       ├── preview-modal.php
│   │       └── recipients-card.php
│   ├── validators/
│   │   └── class-email-validator.php        # Input validation rules
│   ├── repositories/
│   │   ├── class-email-log-db-repository.php    # DB-backed log & draft repository
│   │   ├── class-email-queue-repository.php     # Queue table CRUD
│   │   ├── class-email-log-options-repository.php  # Legacy wp_options repository
│   │   └── class-post-meta-repository.php       # Post meta for auto-email tracking
│   ├── interfaces/
│   │   ├── interface-email-sender.php
│   │   ├── interface-email-template.php
│   │   ├── interface-email-validator.php
│   │   ├── interface-email-logger.php
│   │   ├── interface-markdown-parser.php
│   │   ├── interface-email-log-repository.php
│   │   └── interface-post-meta-repository.php
│   └── exceptions/
│       ├── class-penalis-exception.php
│       ├── class-validation-exception.php
│       ├── class-container-exception.php
│       ├── class-template-exception.php
│       ├── class-repository-exception.php
│       └── class-email-send-exception.php
└── assets/
    ├── css/
    │   └── admin.css                        # Admin interface styles
    └── js/
        └── admin.js                         # Admin interface scripts
```

### Key Components

#### Service Container
Automatic dependency injection and resolution:
```php
// Automatic dependency resolution
$compose_page = Penalis_Service_Container::get(Penalis_Compose_Page::class);
// All dependencies automatically injected!
```

#### Database Manager
Schema versioning and automatic migration:
```php
// Runs on every load — only acts when SCHEMA_VERSION changes
Penalis_Database::install();

// Tables created/upgraded via dbDelta (non-destructive)
// Legacy wp_options data migrated automatically on first run
```

#### Queue Repository
Per-recipient job management:
```php
// Bulk-insert 500 recipients in one query
$queue->bulk_enqueue($job_id, $user_ids, $subject, $body, $from_name);

// Fetch next batch for processing
$items = $queue->get_pending_batch(30);

// Get job progress for UI
$summary = $queue->get_job_summary($job_id);
// Returns: total, sent, pending, failed, permanently_failed, overall
```

#### Repository Pattern
Clean data access abstraction:
```php
interface Penalis_Email_Log_Repository_Interface {
    public function save(array $log_entry): bool;
    public function get_all(int $limit = 0): array;
    public function find_by_id(string $id): ?array;
    public function save_draft(array $draft_data): bool;
    public function update_draft(string $id, array $draft_data): bool;
}
```

#### Validation System
Flexible validation with custom rules:
```php
$validator->validate($data, [
    'subject' => 'required|min:3|max:200',
    'email'   => 'required|email',
    'body'    => 'required|min:10'
]);
```

## Extensibility

### Filters

```php
// Modify logo URL in email templates
apply_filters('penalis_logo_url', $url);

// Modify eligible user roles
apply_filters('penalis_eligible_roles', ['author', 'contributor']);

// Modify automatic email subject
apply_filters('penalis_auto_email_subject', $subject);

// Modify email subject (all emails)
apply_filters('penalis_email_subject', $subject, $post_id);

// Modify email message content
apply_filters('penalis_email_message', $message, $post_id);

// Modify email headers
apply_filters('penalis_email_headers', $headers, $post_id);

// Modify recipient list before queuing
apply_filters('penalis_email_recipients', $user_ids);
```

### Actions

```php
// Fired after a successful send (manual, automatic, or test)
// $data contains: type, subject, recipients, job_id (for manual)
do_action('penalis_email_sent_success', $data);

// Fired when sending fails — receives the exception object
do_action('penalis_email_send_failed', $exception);

// Fired after recipients are queued for background sending
// $data contains: job_id, subject, recipient_count
do_action('penalis_email_queued', $data);

// Fired when some recipients fail within a batch
// $results contains: success, failed, errors arrays
do_action('penalis_email_send_partial_failure', $results);
```

### Example: Custom Email Subject

```php
add_filter('penalis_email_subject', function($subject, $post_id) {
    if ($post_id > 0) {
        $category      = get_the_category($post_id);
        $category_name = !empty($category) ? $category[0]->name : 'General';
        return '[' . $category_name . '] ' . get_the_title($post_id);
    }
    return $subject;
}, 10, 2);
```

### Example: Add Custom Footer

```php
add_filter('penalis_email_message', function($message, $post_id) {
    $footer = '<div style="margin-top: 30px; text-align: center;">
        <p>Follow us: <a href="https://twitter.com/handle">Twitter</a></p>
    </div>';
    return str_replace('</body>', $footer . '</body>', $message);
}, 10, 2);
```

### Example: Log When a Job is Queued

```php
add_action('penalis_email_queued', function($data) {
    error_log(sprintf(
        'Email job %s queued for %d recipients — subject: %s',
        $data['job_id'],
        $data['recipient_count'],
        $data['subject']
    ));
});
```

### Example: Add Reply-To Header

```php
add_filter('penalis_email_headers', function($headers, $post_id) {
    $headers[] = 'Reply-To: Editorial Team <editorial@example.com>';
    return $headers;
}, 10, 2);
```

## Security

### Built-in Security Features

- **Nonce Verification** - All form submissions and AJAX requests protected
- **Capability Checks** - Only administrators (`manage_options`) can access admin interface
- **Input Sanitization** - All user inputs sanitized before processing
- **Output Escaping** - All outputs escaped to prevent XSS
- **SQL Injection Prevention** - Uses WordPress `$wpdb->prepare()` exclusively
- **CSRF Protection** - WordPress nonce system throughout

## License

This plugin is licensed under the GPL v2 or later. See [license](./license.txt) for details.

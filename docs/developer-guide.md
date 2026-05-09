# Developer Guide

This document covers the architecture, file structure, key components, and configuration options for developers working on or extending Penalis Emailer.

## Architecture

The plugin follows clean architecture principles with five distinct layers:

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

## File Structure

```
penalis-emailer/
├── penalis-emailer.php                          # Plugin entry point, bootstrap, hooks
├── uninstall.php                                # Cleanup on plugin deletion
├── includes/
│   ├── class-config.php                         # Centralized configuration constants
│   ├── class-database.php                       # Custom table schema, install, migration
│   ├── class-service-container.php              # Dependency injection container
│   ├── class-email-sender.php                   # Email sending orchestrator (enqueues jobs)
│   ├── class-email-queue-processor.php          # WP-Cron batch processor with retry logic
│   ├── class-email-template.php                 # Template rendering with placeholder support
│   ├── class-email-logger.php                   # Email logging facade
│   ├── class-markdown-parser.php                # Markdown to HTML conversion
│   ├── admin/
│   │   ├── class-admin-interface.php            # Main admin coordinator, menu registration
│   │   ├── class-admin-page.php                 # Base page class (assets, nonces, notices)
│   │   ├── class-compose-page.php               # Compose email page with infinite scroll
│   │   ├── class-draft-page.php                 # Draft management page
│   │   ├── class-history-page.php               # Email history page
│   │   ├── class-settings-page.php              # Template settings page
│   │   ├── class-queue-monitor-page.php         # Queue monitor page
│   │   ├── class-ajax-handler.php               # All AJAX endpoints
│   │   └── views/                               # HTML view partials
│   │       ├── email-content-card.php
│   │       ├── email-details-card.php
│   │       ├── preview-modal.php
│   │       └── recipients-card.php
│   ├── validators/
│   │   └── class-email-validator.php            # Input validation rules
│   ├── repositories/
│   │   ├── class-email-log-db-repository.php    # DB-backed log & draft repository (v2.0.0+)
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
    ├── css/admin.css
    └── js/admin.js
```

## Key Components

### Service Container

Automatic dependency injection and resolution. All classes are registered as singletons in `penalis-emailer.php` and resolved with their dependencies injected automatically via reflection.

```php
// Resolve a class with all dependencies injected automatically
$compose_page = Penalis_Service_Container::get(Penalis_Compose_Page::class);
```

### Database Manager (`class-database.php`)

Manages custom table creation, schema versioning, and data migration.

- Tables are created/upgraded via `dbDelta()` — non-destructive, safe to run repeatedly
- `SCHEMA_VERSION` constant controls when upgrades run — bump it whenever the table structure changes
- On first run after upgrading from v1.x, legacy data in `wp_options` is migrated automatically

```php
// Called on every plugin load — only acts when SCHEMA_VERSION changes
Penalis_Database::install();
```

**Custom tables:**

| Table | Purpose |
|-------|---------|
| `{prefix}_penalis_email_log` | Sent email records (manual and automatic) |
| `{prefix}_penalis_email_queue` | Per-recipient async send jobs |
| `{prefix}_penalis_email_draft` | Saved drafts |

### Email Queue (`class-email-queue-processor.php`)

Processes the send queue in batches via WP-Cron. Each cron run picks up a configurable number of pending items, sends them with a configurable delay between each, and schedules the next run if more items remain.

**Retry backoff schedule:**

| Attempt | Retry after |
|---------|-------------|
| 1st failure | 5 minutes |
| 2nd failure | 15 minutes |
| 3rd failure | Permanently failed |

```php
// Bulk-insert recipients into the queue (single query)
$queue->bulk_enqueue($job_id, $user_ids, $subject, $body, $from_name);

// Fetch next batch for processing
$items = $queue->get_pending_batch(30);

// Get job progress summary for the UI
$summary = $queue->get_job_summary($job_id);
// Returns: total, sent, pending, processing, failed, permanently_failed, overall
```

### Repository Pattern

All data access goes through repository interfaces, making the storage layer swappable without touching business logic.

```php
interface Penalis_Email_Log_Repository_Interface {
    public function save(array $log_entry): bool;
    public function get_all(int $limit = 0): array;
    public function find_by_id(string $id): ?array;
    public function save_draft(array $draft_data): bool;
    public function update_draft(string $id, array $draft_data): bool;
    // ...
}
```

The default implementation (`Penalis_Email_Log_DB_Repository`) uses custom DB tables. The legacy `Penalis_Email_Log_Options_Repository` (wp_options based) is kept for reference but no longer used.

### Validation System

```php
// Validate with built-in rules
$validator->validate($data, [
    'subject' => 'required|min:3|max:200',
    'email'   => 'required|email',
    'body'    => 'required|min:10',
]);

if ($validator->has_errors()) {
    $first_error = $validator->get_first_error();
}
```

### Autoloader

The plugin uses a custom `spl_autoload_register` in `penalis-emailer.php` that maps class name prefixes to file paths. When adding a new class, follow the naming convention and it will be loaded automatically:

| Class name pattern | File location |
|-------------------|---------------|
| `Penalis_Admin_*`, `Penalis_Compose_*`, `Penalis_Queue_*`, etc. | `includes/admin/class-*.php` |
| `Penalis_*_Interface` | `includes/interfaces/interface-*.php` |
| `Penalis_*_Exception` | `includes/exceptions/class-*.php` |
| `Penalis_*_Repository` | `includes/repositories/class-*.php` |
| `Penalis_*_Validator` | `includes/validators/class-*.php` |
| Everything else | `includes/class-*.php` |

---

## Data Flow

Understanding how data moves through the plugin is essential before modifying any part of it. Below are the two main flows.

### Manual Email Send Flow

```
Admin fills Compose form and clicks "Send"
  │
  ▼
Compose_Page::handle_submission()
  ├─ verify_security() — nonce + capability check
  ├─ sanitize_inputs() — sanitize_text_field, wp_kses_post, intval
  └─ Email_Validator::validate_manual_email()
  │
  ▼
Email_Sender::send_manual_email()
  ├─ apply_filters('penalis_email_recipients', $user_ids)
  ├─ validate each user_id — skip invalid users
  ├─ generate job_id
  └─ Queue_Repository::bulk_enqueue() — single INSERT for all recipients
  │
  ▼
Queue_Processor::schedule_next_run()
  └─ wp_schedule_single_event(time() + 5, 'penalis_process_email_queue')
  │
  ▼
HTTP response — redirect with job_id in URL
  └─ JS QueueProgressHandler polls penalis_get_queue_status every 5s
  │
  ▼ (background, ~5 seconds later)
WP-Cron fires 'penalis_process_email_queue'
  │
  ▼
Queue_Processor::process_batch()
  ├─ Queue_Repository::get_pending_batch(30)
  ├─ For each item:
  │   ├─ mark_processing()
  │   ├─ get_userdata()
  │   ├─ Email_Template::render_flexible_email() — replace placeholders → parse markdown → wrap in HTML template
  │   ├─ wp_mail()
  │   ├─ mark_sent() or mark_failed() with retry backoff
  │   └─ usleep(throttle_delay)
  ├─ maybe_log_completed_job() — writes to penalis_email_log when all items sent
  └─ maybe_schedule_next_run() — schedules next batch if pending items remain
```

### Automatic Email Flow (Post Publish)

```
Post status changes to 'publish'
  │
  ▼
WordPress fires 'transition_post_status' hook
  │
  ▼
Email_Sender::handle_post_status_transition()
  ├─ validate_post() — check type=post, not revision, new_status=publish
  ├─ has_email_been_sent() — check post meta to prevent duplicates
  └─ get_userdata($post->post_author)
  │
  ▼
Email_Sender::send_email()
  ├─ apply_filters('penalis_email_subject', ...)
  ├─ apply_filters('penalis_email_message', ...)
  ├─ apply_filters('penalis_email_headers', ...)
  └─ wp_mail() — sent synchronously (single recipient, no queue)
  │
  ▼
Email_Logger::log_automatic_email()
  ├─ save to penalis_email_log table
  └─ update_post_meta('_penalis_email_sent') — prevents duplicate sends
```

---

## Placeholder System

Placeholders are tokens in the email body that get replaced with actual data before the markdown is parsed. They are processed in `Email_Template::replace_user_placeholders()`.

### Available Placeholders

| Placeholder | Replaced with | Available in |
|-------------|---------------|--------------|
| `{USER_NAME}` | User's display name | Manual emails |
| `{USER_EMAIL}` | User's email address | Manual emails |
| `{USERNAME}` | User's login name | Manual emails |
| `{AUTHOR_NAME}` | Post author's display name | Auto emails |
| `{POST_TITLE}` | Post title | Auto emails |
| `{POST_URL}` | Post permalink | Auto emails |
| `{BUTTON_CTA}` | Default "Baca Tulisanmu" button | Auto emails |
| `{DATE}` | Current date (WordPress date format) | Both |
| `{SITE_NAME}` | Blog name from WordPress settings | Both |
| `{SITE_URL}` | Home URL | Both |

### Adding a New Placeholder

All placeholder replacement happens in `Email_Template::replace_user_placeholders()` in `includes/class-email-template.php`. To add a new placeholder:

1. Open `includes/class-email-template.php`
2. Find the `$placeholders` array inside `replace_user_placeholders()`
3. Add your new entry:

```php
private function replace_user_placeholders(string $content, array $user_data): string {
    $placeholders = [
        '{USER_NAME}'  => $user_data['display_name'] ?? '',
        '{USER_EMAIL}' => $user_data['user_email']   ?? '',
        // Add your new placeholder here:
        '{CUSTOM_KEY}' => $user_data['custom_key']   ?? '',
        // ...
    ];

    return str_replace(array_keys($placeholders), array_values($placeholders), $content);
}
```

4. Pass the value through `$user_data` when calling `render_flexible_email()`:

```php
$user_data = [
    'display_name' => $user->display_name,
    'user_email'   => $user->user_email,
    'custom_key'   => 'your value here',
];

$html = $this->template->render_flexible_email($body, $user_data);
```

---

## Markdown Parser

The plugin uses a custom-built markdown parser (`includes/class-markdown-parser.php`) — not an external library. It is intentionally minimal and email-safe: all output uses inline styles compatible with major email clients.

### Supported Syntax

| Syntax | Output |
|--------|--------|
| `**text**` or `__text__` | `<strong>text</strong>` |
| `*text*` or `_text_` | `<em>text</em>` |
| `[link text](url)` | Anchor tag with inline style |
| `[button: Text](url)` | Styled HTML button (table-based for email compatibility) |
| `- item` or `* item` | Unordered list `<ul>` |
| `1. item` | Ordered list `<ol>` |
| Blank line between text | New paragraph `<p>` |
| Single line break | `<br>` within the same paragraph |
| `https://...` (bare URL) | Auto-linked anchor tag |

### What Is NOT Supported

- Headings (`#`, `##`, `###`)
- Blockquotes (`>`)
- Code blocks (` ``` ` or indented)
- Inline code (`` `code` ``)
- Horizontal rules (`---`)
- Nested lists
- Raw HTML (escaped by `esc_html()` before processing)

### Adding New Syntax

The parser processes text in two stages:

1. **`parse()`** — splits text into paragraphs (double newline), then processes each paragraph line by line to detect lists vs regular text
2. **`format_inline_markdown()`** — handles inline formatting (bold, italic, links, buttons) within a single line

To add a new block-level element (like headings), modify `process_paragraph()`. To add a new inline element, modify `format_inline_markdown()`.

**Example: adding heading support**

```php
// In process_paragraph(), add before the list detection:
if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $matches)) {
    $level   = strlen($matches[1]); // 1, 2, or 3
    $heading = $this->format_inline_markdown($matches[2]);
    $sizes   = [1 => '20px', 2 => '17px', 3 => '15px'];
    $size    = $sizes[$level] ?? '15px';
    $paragraph_content .= sprintf(
        '<h%d style="margin:0 0 12px 0;font-size:%s;">%s</h%d>',
        $level, $size, $heading, $level
    );
    continue;
}
```

> **Note:** Always use inline styles in any HTML you add to the parser output. CSS classes are stripped by most email clients.

---

## Database Schema

The plugin creates three custom tables on activation. All tables use the WordPress table prefix (e.g. `wp_`).

### `{prefix}_penalis_email_log`

Stores completed send records for both manual and automatic emails.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `log_key` | `VARCHAR(64) UNIQUE` | Unique string identifier (e.g. `manual_1234_abc`) |
| `type` | `VARCHAR(20)` | `manual` or `automatic` |
| `subject` | `VARCHAR(255)` | Email subject |
| `body_preview` | `TEXT` | First 100 characters of the body (plain text) |
| `job_id` | `VARCHAR(64)` | Queue job ID (empty for automatic emails) |
| `post_id` | `BIGINT UNSIGNED` | Post ID (automatic emails only; `0` for manual) |
| `post_title` | `VARCHAR(255)` | Post title (automatic emails only) |
| `post_url` | `VARCHAR(2083)` | Post URL (automatic emails only) |
| `recipient_count` | `INT UNSIGNED` | Number of recipients |
| `recipients` | `LONGTEXT` | JSON-encoded array of user IDs |
| `recipient_email` | `VARCHAR(255)` | Recipient email (automatic emails only) |
| `recipient_name` | `VARCHAR(255)` | Recipient display name (automatic emails only) |
| `sent_by` | `BIGINT UNSIGNED` | User ID of the admin who sent; `0` for system |
| `sent_at` | `INT UNSIGNED` | Unix timestamp |
| `status` | `VARCHAR(20)` | Always `sent` for log entries |

### `{prefix}_penalis_email_queue`

Stores individual per-recipient send jobs for async processing. One row per recipient per send operation.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `job_id` | `VARCHAR(64)` | Groups all recipients of one send operation |
| `user_id` | `BIGINT UNSIGNED` | WordPress user ID of the recipient |
| `subject` | `VARCHAR(255)` | Email subject |
| `body` | `LONGTEXT` | Email body (markdown source) |
| `from_name` | `VARCHAR(100)` | Sender display name |
| `status` | `VARCHAR(20)` | `pending`, `processing`, `sent`, `failed`, `permanently_failed` |
| `attempts` | `TINYINT UNSIGNED` | Number of send attempts so far |
| `next_attempt` | `INT UNSIGNED` | Unix timestamp — when to next attempt (respects retry backoff) |
| `created_at` | `INT UNSIGNED` | Unix timestamp when job was created |
| `sent_at` | `INT UNSIGNED` | Unix timestamp when successfully sent; `0` if not yet sent |
| `error_message` | `TEXT` | Last error message if failed |

### `{prefix}_penalis_email_draft`

Stores saved email drafts.

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `draft_key` | `VARCHAR(64) UNIQUE` | Unique string identifier (e.g. `draft_1234_abc`) |
| `from_name` | `VARCHAR(100)` | Sender display name |
| `subject` | `VARCHAR(255)` | Email subject |
| `body` | `LONGTEXT` | Email body (markdown source) |
| `recipient_count` | `INT UNSIGNED` | Number of selected recipients |
| `recipients` | `LONGTEXT` | JSON-encoded array of user IDs |
| `created_by` | `BIGINT UNSIGNED` | User ID of the admin who created the draft |
| `updated_by` | `BIGINT UNSIGNED` | User ID of the admin who last edited the draft |
| `created_at` | `INT UNSIGNED` | Unix timestamp |
| `updated_at` | `INT UNSIGNED` | Unix timestamp of last edit |

### Schema Versioning

When modifying any table structure, always bump `SCHEMA_VERSION` in `includes/class-database.php`:

```php
const SCHEMA_VERSION = '2.0.2'; // increment this
```

On the next plugin load, `version_compare` detects the change and calls `Penalis_Database::install()`, which runs `dbDelta()` to apply the new schema non-destructively.

---

## Configuration

All developer-facing configuration lives in `includes/class-config.php`. Values marked as "UI configurable" can also be changed from **Penalis Email → Queue Monitor** without editing code.

| Constant | Default | UI configurable | Description |
|----------|---------|-----------------|-------------|
| `RECIPIENTS_INITIAL_LOAD` | `30` | No | Users rendered on initial Compose page load (infinite scroll batch size) |
| `DEFAULT_QUEUE_BATCH_SIZE` | `30` | Yes | Emails sent per cron run |
| `DEFAULT_QUEUE_INTERVAL` | `60` | Yes | Seconds between cron batches (minimum: 30) |
| `DEFAULT_QUEUE_MAX_ATTEMPTS` | `3` | Yes | Max retry attempts before permanently failed |
| `DEFAULT_QUEUE_THROTTLE_DELAY` | `500000` | Yes | Microseconds between emails within a batch (500 ms) |

## Security

All user-facing entry points implement the following:

- **Nonce verification** — all form submissions and AJAX requests
- **Capability checks** — `current_user_can('manage_options')` on every admin action
- **Input sanitization** — `sanitize_text_field()`, `wp_kses_post()`, `intval()` as appropriate
- **Output escaping** — `esc_html()`, `esc_attr()`, `esc_url()` on all output
- **Prepared statements** — `$wpdb->prepare()` for all direct DB queries

## Deployment

The repository includes a GitHub Actions workflow (`.github/workflows/release.yml`) that automatically builds a production ZIP and creates a GitHub Release whenever a version tag is pushed.

```bash
# Tag and push to trigger a release build
git tag v2.1.0
git push origin v2.1.0
```

The resulting ZIP contains only production files (`penalis-emailer.php`, `uninstall.php`, `readme.txt`, `license.txt`, `includes/`, `assets/`) — development files such as `README.md`, `CHANGELOG.md`, and `.github/` are excluded.

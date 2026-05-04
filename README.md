# Penalis Emailer

A WordPress plugin that automatically notifies post authors via HTML email when their posts are published, with a comprehensive administrative interface for manual email management.

**Note:** This plugin was originally developed for internal use at [Penalis](https://penalis.com) to manage post author notifications on our website. However, we've made it publicly available so others can use it in their projects if they find it useful.

## Features

### Core Functionality
- **Automatic Notifications** - Sends HTML email notifications to post authors when posts are published
- **Manual Email Interface** - Admin interface for sending emails to selected users with markdown support
- **HTML Email Templates** - Professional, responsive templates with dynamic placeholder replacement
- **Duplicate Prevention** - Prevents sending multiple emails for the same post publication
- **Email History & Management** - Comprehensive email tracking with separate tabs for manual and automatic emails, bulk delete operations, and clear all functionality
- **Markdown Support** - Write emails in markdown with automatic HTML conversion

### Technical Features
- **Service Container** - Automatic dependency injection and resolution
- **Repository Pattern** - Clean data access abstraction layer
- **Validation System** - Flexible, reusable validation with custom rules
- **Exception Handling** - Comprehensive error handling with custom exception classes
- **Interface-Based Design** - 6 interfaces for loose coupling and testability
- **SMTP Compatible** - Works seamlessly with any SMTP plugin

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- No external dependencies required

## Installation

1. Download the plugin files
2. Upload the `penalis-emailer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. (Optional) Configure SMTP settings for reliable email delivery

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

1. Enter email subject
2. Write message in markdown or HTML
3. Select recipients from authors and contributors
4. Preview before sending
5. Click **Send Email**

### Template Customization

Navigate to **Penalis Email** → **Template Settings**:

1. Edit the automatic email template
2. Use placeholders: `{AUTHOR_NAME}`, `{POST_TITLE}`, `{POST_URL}`
3. Write in markdown for easy formatting
4. Preview changes before saving

### Email History

Navigate to **Penalis Email** → **Email History**:

**Features:**
- **Separate Tabs** - View manual and automatic emails in dedicated tabs
- **Comprehensive Tracking** - All sent emails logged with timestamp, recipients, and status
- **Bulk Delete** - Select multiple emails and delete them at once
- **Clear All History** - Remove all emails from a specific tab (manual or automatic)
- **Legacy Support** - Handles old email entries without IDs seamlessly
- **WordPress-Style Interface** - Familiar bulk actions interface matching WordPress Posts

**Manual Email Tab:**
- Subject, Recipients count, Sent By, Sent At, Status
- Track who sent the email and when

**Automatic Email Tab:**
- Post title with link, Recipient name, Sent At, Status
- Optimized columns for automatic notifications

**Delete Operations:**
- Check one or more emails and use bulk delete
- Clear all history for manual or automatic emails separately
- Double confirmation for safety on clear all operations

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
│  (Email Sender, Template, Parser)       │
├─────────────────────────────────────────┤
│           Data Layer                    │
│  (Repositories, Logger)                 │
├─────────────────────────────────────────┤
│       Infrastructure Layer              │
│  (WordPress APIs, Service Container)    │
└─────────────────────────────────────────┘
```

### File Structure

```
penalis-emailer/
├── penalis-emailer.php              # Plugin entry point
├── includes/
│   ├── class-config.php             # Configuration management
│   ├── class-service-container.php  # Dependency injection container
│   ├── class-email-sender.php       # Email sending orchestrator
│   ├── class-email-template.php     # Template rendering
│   ├── class-email-logger.php       # Email logging
│   ├── class-markdown-parser.php    # Markdown to HTML conversion
│   ├── admin/
│   │   ├── class-admin-interface.php    # Main admin coordinator
│   │   ├── class-admin-page.php         # Base page class
│   │   ├── class-compose-page.php       # Compose email page
│   │   ├── class-history-page.php       # Email history page
│   │   ├── class-settings-page.php      # Template settings page
│   │   ├── class-ajax-handler.php       # AJAX endpoints
│   │   └── views/                       # HTML view templates
│   ├── validators/
│   │   └── class-email-validator.php    # Input validation
│   ├── repositories/
│   │   ├── class-email-log-options-repository.php
│   │   └── class-post-meta-repository.php
│   ├── interfaces/
│   │   ├── interface-email-sender.php
│   │   ├── interface-email-template.php
│   │   ├── interface-email-validator.php
│   │   ├── interface-email-logger.php
│   │   ├── interface-markdown-parser.php
│   │   └── interface-email-log-repository.php
│   └── exceptions/
│       ├── class-penalis-exception.php
│       ├── class-validation-exception.php
│       ├── class-container-exception.php
│       ├── class-template-exception.php
│       ├── class-repository-exception.php
│       └── class-email-send-exception.php
└──assets/
    ├── css/
    │   └── admin.css              # Admin interface styles
    └── js/
      └── admin.js                 # Admin interface scripts
```

### Key Components

#### Service Container
Automatic dependency injection and resolution:
```php
// Automatic dependency resolution
$compose_page = Penalis_Service_Container::get(Penalis_Compose_Page::class);
// All dependencies automatically injected!
```

#### Repository Pattern
Clean data access abstraction:
```php
interface Penalis_Email_Log_Repository_Interface {
    public function save(array $log_entry): bool;
    public function get_all(int $limit = 0): array;
    public function find_by_id(string $id): ?array;
}
```

#### Validation System
Flexible validation with custom rules:
```php
$validator->validate($data, [
    'subject' => 'required|min:3|max:200',
    'email' => 'required|email',
    'body' => 'required|min:10'
]);
```

#### Exception Handling
Comprehensive error handling:
```php
try {
    $sender->send_manual_email($subject, $recipients, $body);
} catch (Penalis_Email_Send_Exception $e) {
    error_log($e->to_json());
    $failed = $e->get_failed_recipients();
}
```

## Extensibility

### Filters

```php
// Modify logo URL
apply_filters('penalis_logo_url', $url);

// Modify eligible roles
apply_filters('penalis_eligible_roles', ['author', 'contributor']);

// Modify email subject
apply_filters('penalis_email_subject', $subject, $post_id);

// Modify email message
apply_filters('penalis_email_message', $message, $post_id);

// Modify email headers
apply_filters('penalis_email_headers', $headers, $post_id);

// Modify recipients
apply_filters('penalis_email_recipients', $user_ids);
```

### Actions

```php
// Before email send
do_action('penalis_before_email_send', $email_data);

// After email send
do_action('penalis_after_email_send', $email_data, $result);

// After email send failure
do_action('penalis_email_send_failed', $exception);
```

### Example: Custom Email Subject

```php
add_filter('penalis_email_subject', function($subject, $post_id) {
    if ($post_id > 0) {
        $category = get_the_category($post_id);
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

## Security

### Built-in Security Features

- **Nonce Verification** - All form submissions protected
- **Capability Checks** - Only administrators can access admin interface
- **Input Sanitization** - All user inputs sanitized before processing
- **Output Escaping** - All outputs escaped to prevent XSS
- **SQL Injection Prevention** - Uses WordPress APIs exclusively
- **CSRF Protection** - WordPress nonce system
- **Validation** - Comprehensive input validation

### Security Best Practices

```php
// Nonce verification
wp_verify_nonce($_POST['nonce'], 'action_name');

// Capability check
current_user_can('manage_options');

// Input sanitization
sanitize_text_field($input);
wp_kses_post($html);

// Output escaping
esc_html($text);
esc_attr($attr);
esc_url($url);
```

## License

This plugin is licensed under the GPL v2 or later. See [license](./license.txt) for details.

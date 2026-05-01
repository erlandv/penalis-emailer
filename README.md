# Penalis Emailer

A WordPress plugin that automatically notifies post authors via HTML email when their posts are published, with an administrative interface for manually sending emails to selected users.

## Features

- **Automatic Notifications**: Sends HTML email notifications to post authors when their posts are published
- **Manual Email Interface**: Admin interface for sending emails to selected authors and contributors
- **HTML Email Templates**: Professional, responsive HTML email templates with dynamic placeholder replacement
- **Duplicate Prevention**: Prevents sending multiple emails for the same post publication
- **Email Logging**: Tracks all sent emails for auditing and troubleshooting
- **Security-First**: Implements nonces, capability checks, and input sanitization
- **SMTP Compatible**: Works seamlessly with any SMTP plugin
- **Extensible**: Provides filters for customizing email content, subjects, headers, and recipients

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- No external dependencies required

## Installation

1. Download the plugin files
2. Upload the `penalis-emailer` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your SMTP settings (optional, but recommended for reliable email delivery)

## Usage

### Automatic Email Notifications

Once activated, the plugin automatically sends email notifications to post authors when their posts are published. No configuration needed.

**What triggers an email:**
- Post status changes from any non-published status to "publish"
- Post type is "post" (not pages or custom post types)
- Post is not a revision
- Email has not been sent previously for this post

**Email content includes:**
- Personalized greeting with author's name
- Post title
- Link to the published post
- Professional HTML template with branding

### Manual Email Sending

Administrators can manually send emails to selected users:

1. Navigate to **Penalis Email** in the WordPress admin menu
2. Enter an email subject
3. Choose between using the default template or providing a custom message
4. Select recipients from the list of authors and contributors
5. Click **Send Email**

## Extensibility

The plugin provides several filters for customization without modifying core plugin files.

### Available Filters

#### 1. `penalis_email_subject`

Customize the email subject line.

**Parameters:**
- `$subject` (string): The default email subject
- `$post_id` (int): The post ID (0 for manual emails)

**Example:**

```php
add_filter('penalis_email_subject', function($subject, $post_id) {
    if ($post_id > 0) {
        $post = get_post($post_id);
        $category = get_the_category($post_id);
        $category_name = !empty($category) ? $category[0]->name : 'General';
        return '[' . $category_name . '] ' . $post->post_title . ' - Published!';
    }
    return $subject;
}, 10, 2);
```

#### 2. `penalis_email_message`

Customize the email message content.

**Parameters:**
- `$message` (string): The email HTML content
- `$post_id` (int): The post ID (0 for manual emails)

**Example:**

```php
add_filter('penalis_email_message', function($message, $post_id) {
    // Add custom footer with social media links
    $custom_footer = '
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="text-align: center; color: #666;">
                Follow us on social media:<br>
                <a href="https://twitter.com/yourhandle">Twitter</a> | 
                <a href="https://facebook.com/yourpage">Facebook</a> | 
                <a href="https://instagram.com/yourprofile">Instagram</a>
            </p>
        </div>
    ';
    
    // Insert before closing body tag
    $message = str_replace('</body>', $custom_footer . '</body>', $message);
    
    return $message;
}, 10, 2);
```

#### 3. `penalis_email_headers`

Customize email headers (Reply-To, CC, BCC, etc.).

**Parameters:**
- `$headers` (array): Array of email headers
- `$post_id` (int): The post ID (0 for manual emails)

**Example:**

```php
add_filter('penalis_email_headers', function($headers, $post_id) {
    // Add Reply-To header
    $headers[] = 'Reply-To: Editorial Team <editorial@example.com>';
    
    // Add CC for all post publication emails
    if ($post_id > 0) {
        $headers[] = 'Cc: editor@example.com';
    }
    
    return $headers;
}, 10, 2);
```

#### 4. `penalis_email_recipients`

Modify the recipient list for manual emails.

**Parameters:**
- `$user_ids` (array): Array of user IDs to receive the email

**Example:**

```php
add_filter('penalis_email_recipients', function($user_ids) {
    // Always include the site administrator
    $admin_user = get_user_by('email', 'admin@example.com');
    if ($admin_user) {
        $user_ids[] = $admin_user->ID;
    }
    
    // Remove duplicates
    $user_ids = array_unique($user_ids);
    
    // Exclude specific users
    $excluded_users = [5, 12, 23]; // User IDs to exclude
    $user_ids = array_diff($user_ids, $excluded_users);
    
    return $user_ids;
}, 10, 1);
```

## SMTP Plugin Compatibility

The Penalis Emailer uses WordPress's native `wp_mail()` function for sending emails, making it fully compatible with any SMTP plugin. The plugin does not override or manipulate PHPMailer directly.

**Recommended SMTP Plugins:**
- WP Mail SMTP
- Easy WP SMTP
- Post SMTP
- FluentSMTP

**Setup:**
1. Install and configure your preferred SMTP plugin
2. The Penalis Emailer will automatically use your SMTP settings
3. No additional configuration needed

## Email Template Placeholders

The HTML email template uses the following placeholders:

- `AUTHOR_NAME` - Replaced with the recipient's display name
- `POST_TITLE` - Replaced with the post title
- `POST_URL` - Replaced with the post permalink

All placeholder values are automatically escaped to prevent XSS vulnerabilities.

## Security Features

- **Nonce Verification**: All form submissions are protected with WordPress nonces
- **Capability Checks**: Only users with `manage_options` capability can access the admin interface
- **Input Sanitization**: All user inputs are sanitized before processing
- **Output Escaping**: All outputs are escaped to prevent XSS attacks
- **SQL Injection Prevention**: Uses WordPress APIs exclusively (no direct SQL queries)

## Email Logging

The plugin logs all sent emails for auditing purposes:

- **Automatic emails**: Logged via post meta (`_penalis_email_sent`)
- **Manual emails**: Logged via WordPress options API (`penalis_manual_emails_log`)

## Troubleshooting

### Emails not being sent

1. **Check SMTP configuration**: Ensure your SMTP plugin is properly configured
2. **Check spam folder**: Emails might be filtered as spam
3. **Enable WordPress debug logging**: Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
4. **Check error logs**: Look for errors in `wp-content/debug.log`

### Duplicate emails

The plugin prevents duplicate emails by checking post meta. If you're receiving duplicates:

1. Check if multiple plugins are sending post notifications
2. Verify the `_penalis_email_sent` post meta is being set correctly

### Admin interface not visible

1. Ensure you're logged in as an Administrator
2. Check that the plugin is activated
3. Clear browser cache and WordPress cache

## Development

### File Structure

```
penalis-emailer/
├── penalis-emailer.php          # Main plugin file
├── includes/
│   ├── class-email-sender.php   # Email sending logic
│   ├── class-admin-interface.php # Admin interface
│   ├── class-email-template.php  # Email template rendering
│   └── class-email-logger.php    # Email logging
└── README.md                     # This file
```

### Class Architecture

- **Penalis_Email_Sender**: Handles email composition and sending
- **Penalis_Admin_Interface**: Manages the WordPress admin interface
- **Penalis_Email_Template**: Renders HTML email templates with placeholder replacement
- **Penalis_Email_Logger**: Tracks email delivery for auditing

## Support

For issues, questions, or feature requests, please contact the plugin author or submit an issue in the plugin repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Automatic email notifications on post publish
- Manual email sending interface
- HTML email templates
- Email logging
- SMTP plugin compatibility
- Extensibility filters

=== Penalis Emailer ===
Contributors: penalis
Tags: email, notification, post, author, smtp, markdown, admin
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically notify post authors via HTML email when posts are published, with a comprehensive admin interface for manual email management.

== Description ==

Penalis Emailer is a professional WordPress plugin that streamlines communication with your content creators. It automatically sends beautiful HTML email notifications to post authors when their posts are published, and provides a powerful admin interface for sending custom emails to selected users.

**Note:** This plugin was originally developed for internal use at Penalis to manage post author notifications on our website. However, we've made it publicly available so others can use it in their projects if they find it useful.

= Key Features =

* **Automatic Notifications** - Sends HTML email notifications to post authors when their posts are published
* **Manual Email Interface** - Comprehensive admin interface for sending emails to selected users
* **Markdown Support** - Write emails in markdown with automatic HTML conversion
* **HTML Email Templates** - Professional, responsive email templates with dynamic placeholders
* **Email Preview** - Preview emails before sending
* **Email History & Management** - Dedicated history page with separate tabs for manual and automatic emails, bulk delete, and clear all functionality
* **Duplicate Prevention** - Prevents sending multiple emails for the same post
* **SMTP Compatible** - Works seamlessly with any SMTP plugin
* **Highly Extensible** - Rich filter and action hooks for customization
* **Security First** - Implements nonces, capability checks, and input sanitization

= Technical Highlights =

* **Clean Architecture** - Built with SOLID principles and modern design patterns
* **Service Container** - Automatic dependency injection and resolution
* **Repository Pattern** - Clean data access abstraction
* **Validation System** - Flexible, reusable validation with custom rules
* **Exception Handling** - Comprehensive error handling
* **110+ Unit Tests** - Thoroughly tested with 100% pass rate
* **Well Documented** - Extensive inline documentation and architecture guides

= Use Cases =

* **Multi-Author Blogs** - Notify authors when their posts go live
* **Editorial Workflow** - Keep contributors informed about publication status
* **Content Teams** - Send announcements and updates to selected team members
* **Newsletter Alternative** - Send custom HTML emails to authors and contributors
* **Audit Trail** - Track all email communications with comprehensive logging

= Automatic Email Notifications =

Once activated, the plugin automatically sends email notifications to post authors when their posts are published. The email includes:

* Personalized greeting with author's name
* Post title
* Link to the published post
* Professional HTML template

= Manual Email Sending =

The admin interface allows you to:

* Compose emails in markdown or HTML
* Select recipients from authors and contributors
* Preview emails before sending
* Use dynamic placeholders
* Track email history in dedicated history page

= Email History & Management =

View and manage all sent emails in a dedicated history page:

* **Separate Tabs** - Manual and automatic emails in dedicated tabs for better organization
* **Comprehensive Tracking** - All emails logged with timestamp, recipients, sender, and status
* **Bulk Delete** - Select multiple emails and delete them at once using WordPress-style bulk actions
* **Clear All History** - Remove all emails from a specific tab (manual or automatic) with double confirmation
* **Optimized Views** - Manual tab shows subject, recipients, sent by, and timestamp; Automatic tab shows post title, recipient, and timestamp
* **Legacy Support** - Seamlessly handles old email entries from previous versions

Delete operations:
* Check one or more email entries and use bulk delete action
* Clear all history for manual or automatic emails separately
* Double confirmation required for clear all operations for safety

= Email Template Customization =

Customize the automatic email template with:

* Markdown support for easy formatting
* Dynamic placeholders: `{AUTHOR_NAME}`, `{POST_TITLE}`, `{POST_URL}`
* Live preview
* Reset to default option

= Developer Friendly =

Penalis Emailer is built with developers in mind:

* Clean, well-documented code
* SOLID principles throughout
* Comprehensive filter and action hooks
* Easy to extend and customize
* Repository pattern for data access
* Service container for dependency injection

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Penalis Emailer"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"

= After Activation =

1. Navigate to **Penalis Email** in the WordPress admin menu
2. (Optional) Configure your SMTP settings using an SMTP plugin for reliable delivery
3. (Optional) Customize the email template in **Penalis Email → Settings**

== Frequently Asked Questions ==

= Does this plugin send emails automatically? =

Yes! Once activated, the plugin automatically sends email notifications to post authors when their posts are published. No configuration needed.

= Can I send custom emails to selected users? =

Absolutely! Navigate to **Penalis Email → Compose** to send custom emails to selected authors and contributors.

= Does it work with SMTP plugins? =

Yes! Penalis Emailer uses WordPress's native `wp_mail()` function, making it fully compatible with any SMTP plugin like WP Mail SMTP, Easy WP SMTP, Post SMTP, or FluentSMTP.

= Can I customize the email template? =

Yes! Navigate to **Penalis Email → Settings** to customize the automatic email template. You can use markdown for easy formatting and dynamic placeholders.

= What placeholders are available? =

The following placeholders are available:
* `{AUTHOR_NAME}` - Recipient's display name
* `{POST_TITLE}` - Post title
* `{POST_URL}` - Post permalink

= Can I preview emails before sending? =

Yes! The compose page includes a preview button that shows exactly how your email will look.

= Are emails logged? =

Yes! All sent emails are logged for auditing. View the history in **Penalis Email → Email History**. The history page has separate tabs for manual and automatic emails, with bulk delete and clear all functionality.

= Will it send duplicate emails? =

No! The plugin prevents sending multiple emails for the same post publication by tracking sent emails via post meta.

= Can I customize the plugin behavior? =

Yes! The plugin provides numerous filters and actions for customization. See the documentation for details.

= Is it secure? =

Yes! The plugin implements:
* Nonce verification for all form submissions
* Capability checks (only administrators can access)
* Input sanitization and output escaping
* SQL injection prevention via WordPress APIs
* CSRF protection

= Does it support markdown? =

Yes! You can write emails in markdown and the plugin will automatically convert them to HTML.

= What happens if email sending fails? =

The plugin includes comprehensive error handling and will display appropriate error messages. Failed sends are logged for troubleshooting.

= Can I use it with custom post types? =

Currently, automatic notifications only work with the standard "post" post type. However, you can use the manual email interface for any use case.

= Is it translation ready? =

Yes! The plugin uses WordPress translation functions and is ready for translation.

== Screenshots ==

1. Compose email interface with markdown support and recipient selection
2. Email history page showing all sent emails with filtering options
3. Template settings page with live preview
4. Email preview modal showing how the email will look
5. Automatic email notification received by post author


= Filters =

**penalis_logo_url**
Modify the logo URL in email templates.
`add_filter('penalis_logo_url', function($url) { return $custom_url; });`

**penalis_eligible_roles**
Modify the user roles eligible for receiving emails.
`add_filter('penalis_eligible_roles', function($roles) { return ['author', 'contributor', 'editor']; });`

**penalis_email_subject**
Customize the email subject line.
`add_filter('penalis_email_subject', function($subject, $post_id) { return $custom_subject; }, 10, 2);`

**penalis_email_message**
Customize the email message content.
`add_filter('penalis_email_message', function($message, $post_id) { return $custom_message; }, 10, 2);`

**penalis_email_headers**
Customize email headers (Reply-To, CC, BCC, etc.).
`add_filter('penalis_email_headers', function($headers, $post_id) { return $custom_headers; }, 10, 2);`

**penalis_email_recipients**
Modify the recipient list for manual emails.
`add_filter('penalis_email_recipients', function($user_ids) { return $modified_user_ids; });`

= Actions =

**penalis_before_email_send**
Fired before an email is sent.
`add_action('penalis_before_email_send', function($email_data) { /* Your code */ });`

**penalis_after_email_send**
Fired after an email is sent.
`add_action('penalis_after_email_send', function($email_data, $result) { /* Your code */ }, 10, 2);`

**penalis_email_send_failed**
Fired when email sending fails.
`add_action('penalis_email_send_failed', function($exception) { /* Your code */ });`

= Code Examples =

**Custom Email Subject with Category**
`
add_filter('penalis_email_subject', function($subject, $post_id) {
    if ($post_id > 0) {
        $category = get_the_category($post_id);
        $category_name = !empty($category) ? $category[0]->name : 'General';
        return '[' . $category_name . '] ' . get_the_title($post_id);
    }
    return $subject;
}, 10, 2);
`

**Add Custom Footer to Emails**
`
add_filter('penalis_email_message', function($message, $post_id) {
    $footer = '<div style="margin-top: 30px; text-align: center;">
        <p>Follow us on social media</p>
    </div>';
    return str_replace('</body>', $footer . '</body>', $message);
}, 10, 2);
`

**Add Reply-To Header**
`
add_filter('penalis_email_headers', function($headers, $post_id) {
    $headers[] = 'Reply-To: Editorial Team <editorial@example.com>';
    return $headers;
}, 10, 2);
`

= Architecture =

The plugin is built with clean architecture principles:

* **Presentation Layer** - Admin pages, views, assets
* **Application Layer** - Controllers, AJAX handlers, validators
* **Business Layer** - Email sender, template renderer, markdown parser
* **Data Layer** - Repositories, logger
* **Infrastructure Layer** - WordPress APIs, service container

For detailed architecture documentation, see the `docs/` folder in the plugin directory.

= Support =

For support, feature requests, or bug reports, please visit the plugin's support forum or GitHub repository.

== Privacy Policy ==

Penalis Emailer does not collect, store, or transmit any personal data outside of your WordPress installation. All email logs are stored locally in your WordPress database.

The plugin:
* Does not send data to external servers
* Does not use cookies
* Does not track users
* Only stores email logs locally for auditing purposes

Email addresses and content are only used for sending emails via your configured email system (WordPress default or SMTP plugin).

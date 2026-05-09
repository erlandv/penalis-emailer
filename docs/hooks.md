# Hooks Reference

All filters and actions provided by Penalis Emailer for customization and integration.

## Filters

### `penalis_logo_url`

Modify the logo URL used in email templates.

```php
add_filter('penalis_logo_url', function(string $url): string {
    return 'https://example.com/my-logo.png';
});
```

---

### `penalis_eligible_roles`

Modify the WordPress user roles that are eligible to receive manual emails and appear in the recipients list.

**Default:** `['author', 'contributor']`

```php
add_filter('penalis_eligible_roles', function(array $roles): array {
    $roles[] = 'editor';
    return $roles;
});
```

---

### `penalis_auto_email_subject`

Customize the subject line of automatic post-publish notification emails.

```php
add_filter('penalis_auto_email_subject', function(string $subject): string {
    return 'Your post is now live on ' . get_bloginfo('name');
});
```

---

### `penalis_email_subject`

Customize the subject line for any outgoing email (manual and automatic).

**Parameters:**
- `$subject` *(string)* — the current subject
- `$post_id` *(int)* — post ID for automatic emails; `0` for manual emails

```php
add_filter('penalis_email_subject', function(string $subject, int $post_id): string {
    if ($post_id > 0) {
        $categories    = get_the_category($post_id);
        $category_name = !empty($categories) ? $categories[0]->name : 'General';
        return '[' . $category_name . '] ' . get_the_title($post_id);
    }
    return $subject;
}, 10, 2);
```

---

### `penalis_email_message`

Modify the rendered HTML body of any outgoing email.

**Parameters:**
- `$message` *(string)* — the rendered HTML email body
- `$post_id` *(int)* — post ID for automatic emails; `0` for manual emails

```php
add_filter('penalis_email_message', function(string $message, int $post_id): string {
    $footer = '<div style="margin-top:30px;text-align:center;">
        <p>Follow us on <a href="https://twitter.com/handle">Twitter</a></p>
    </div>';
    return str_replace('</body>', $footer . '</body>', $message);
}, 10, 2);
```

---

### `penalis_email_headers`

Modify the email headers array (e.g. add Reply-To, CC, BCC).

**Parameters:**
- `$headers` *(array)* — current headers array
- `$post_id` *(int)* — post ID for automatic emails; `0` for manual emails

```php
add_filter('penalis_email_headers', function(array $headers, int $post_id): array {
    $headers[] = 'Reply-To: Editorial Team <editorial@example.com>';
    return $headers;
}, 10, 2);
```

---

### `penalis_email_recipients`

Modify the list of recipient user IDs before they are inserted into the send queue.

**Parameters:**
- `$user_ids` *(array)* — array of WordPress user IDs

```php
// Exclude a specific user from all manual sends
add_filter('penalis_email_recipients', function(array $user_ids): array {
    return array_filter($user_ids, fn($id) => $id !== 42);
});
```

---

## Actions

### `penalis_email_sent_success`

Fired after an email is sent successfully. Applies to manual, automatic, and test emails.

**Parameters:**
- `$data` *(array)* — context data. Keys vary by type:
  - Manual: `type`, `subject`, `success_count`, `recipients` (array of user IDs), `job_id`
  - Automatic: `type`, `post_id`, `recipient` (email address)
  - Test: `type`, `recipient` (email address)

```php
add_action('penalis_email_sent_success', function(array $data): void {
    if ($data['type'] === 'manual') {
        error_log('Manual email sent — job: ' . $data['job_id']);
    }
});
```

---

### `penalis_email_send_failed`

Fired when an email fails to send. Receives the exception object for detailed error context.

**Parameters:**
- `$exception` *(Penalis_Exception)* — the exception that caused the failure

```php
add_action('penalis_email_send_failed', function(Penalis_Exception $exception): void {
    error_log('Email send failed: ' . $exception->getMessage());
});
```

---

### `penalis_email_queued`

Fired after recipients are successfully inserted into the send queue (i.e. after clicking Send on the Compose page).

**Parameters:**
- `$data` *(array)* — keys: `job_id`, `subject`, `recipient_count`

```php
add_action('penalis_email_queued', function(array $data): void {
    error_log(sprintf(
        'Job %s queued — %d recipients, subject: %s',
        $data['job_id'],
        $data['recipient_count'],
        $data['subject']
    ));
});
```

---

### `penalis_email_send_partial_failure`

Fired when a manual email batch completes but some recipients failed.

**Parameters:**
- `$results` *(array)* — keys: `success` (int), `failed` (array of user IDs), `errors` (array keyed by user ID)

```php
add_action('penalis_email_send_partial_failure', function(array $results): void {
    foreach ($results['failed'] as $user_id) {
        error_log('Failed to send to user ' . $user_id . ': ' . ($results['errors'][$user_id] ?? 'unknown'));
    }
});
```

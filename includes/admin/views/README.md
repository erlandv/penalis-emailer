# Admin View Files

This directory contains reusable view templates for the admin interface.

## View Files

### email-details-card.php
Displays the email from name and subject input fields.

**Required Variables:** None (uses global constants)

**Usage:**
```php
require PENALIS_EMAILER_PATH . 'includes/admin/views/email-details-card.php';
```

---

### email-content-card.php
Displays the email body textarea with markdown formatting guide and placeholder documentation.

**Required Variables:** None

**Usage:**
```php
require PENALIS_EMAILER_PATH . 'includes/admin/views/email-content-card.php';
```

---

### recipients-card.php
Displays the user selection table with search, filtering, and pagination functionality.

**Required Variables:**
- `$users` (array) - Array of WP_User objects
- `$current_page` (int) - Current page number
- `$total_pages` (int) - Total number of pages
- `$total_users` (int) - Total number of users

**Usage:**
```php
$users = get_users(['role__in' => ['author', 'contributor']]);
$current_page = 1;
$total_pages = 5;
$total_users = 100;
require PENALIS_EMAILER_PATH . 'includes/admin/views/recipients-card.php';
```

---

### preview-modal.php
Displays the modal dialog for previewing email content before sending.

**Required Variables:** None

**Usage:**
```php
require PENALIS_EMAILER_PATH . 'includes/admin/views/preview-modal.php';
```

---

## Conventions

1. **File Naming**: Use kebab-case (e.g., `email-content-card.php`)
2. **Security**: All view files must check `ABSPATH` to prevent direct access
3. **Documentation**: Include docblock with description and `@var` for required variables
4. **Escaping**: Always escape output using `esc_html()`, `esc_attr()`, `esc_url()`
5. **Translation**: Use `__()` or `esc_html__()` for all user-facing strings
6. **Include Method**: Use `require` (not `require_once`) for consistency

## Adding New View Files

When creating a new view file:

1. Add file header with description and `@since` tag
2. Add `ABSPATH` security check
3. Document required variables with `@var` tags
4. Use proper escaping for all output
5. Make all strings translatable
6. Update this README with usage documentation

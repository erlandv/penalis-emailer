<?php
/**
 * Email Validator Class
 *
 * Handles validation of email-related data with reusable rules.
 * Provides centralized validation logic and error messages.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Validator
 *
 * Validates email data with comprehensive rules.
 */
class Penalis_Email_Validator {
    
    /**
     * Validation errors
     *
     * @var array
     */
    private $errors = [];
    
    /**
     * Validate manual email data
     *
     * @param array $data Email data to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_manual_email(array $data): bool {
        $this->errors = [];
        
        // Validate from_name
        if (!$this->validate_from_name($data['from_name'] ?? '')) {
            $this->errors['from_name'] = __('Email from name is required and must be at least 2 characters.', 'penalis-emailer');
        }
        
        // Validate subject
        if (!$this->validate_subject($data['subject'] ?? '')) {
            $this->errors['subject'] = __('Email subject is required and must be at least 3 characters.', 'penalis-emailer');
        }
        
        // Validate body
        if (!$this->validate_body($data['body'] ?? '')) {
            $this->errors['body'] = __('Email body is required and must be at least 10 characters.', 'penalis-emailer');
        }
        
        // Validate recipients
        if (!$this->validate_recipients($data['user_ids'] ?? [])) {
            $this->errors['user_ids'] = __('Please select at least one recipient.', 'penalis-emailer');
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate template data
     *
     * @param array $data Template data to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_template(array $data): bool {
        $this->errors = [];
        
        // Validate template body
        if (!$this->validate_template_body($data['email_body'] ?? '')) {
            $this->errors['email_body'] = __('Template body is required and must be at least 20 characters.', 'penalis-emailer');
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate from name
     *
     * @param string $from_name From name
     * @return bool True if valid, false otherwise
     */
    public function validate_from_name(string $from_name): bool {
        $from_name = trim($from_name);
        return !empty($from_name) && mb_strlen($from_name) >= 2;
    }
    
    /**
     * Validate subject
     *
     * @param string $subject Email subject
     * @return bool True if valid, false otherwise
     */
    public function validate_subject(string $subject): bool {
        $subject = trim($subject);
        return !empty($subject) && mb_strlen($subject) >= 3 && mb_strlen($subject) <= 200;
    }
    
    /**
     * Validate body
     *
     * @param string $body Email body
     * @return bool True if valid, false otherwise
     */
    public function validate_body(string $body): bool {
        $body = trim($body);
        return !empty($body) && mb_strlen($body) >= 10;
    }
    
    /**
     * Validate recipients
     *
     * @param array $user_ids Array of user IDs
     * @return bool True if valid, false otherwise
     */
    public function validate_recipients(array $user_ids): bool {
        if (empty($user_ids)) {
            return false;
        }
        
        // Check if all are valid integers
        foreach ($user_ids as $user_id) {
            if (!is_numeric($user_id) || $user_id <= 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate template body
     *
     * @param string $template_body Template body content
     * @return bool True if valid, false otherwise
     */
    public function validate_template_body(string $template_body): bool {
        $template_body = trim($template_body);
        return !empty($template_body) && mb_strlen($template_body) >= 20;
    }
    
    /**
     * Validate email address
     *
     * @param string $email Email address
     * @return bool True if valid, false otherwise
     */
    public function validate_email(string $email): bool {
        return is_email($email);
    }
    
    /**
     * Validate user ID exists
     *
     * @param int $user_id User ID
     * @return bool True if valid, false otherwise
     */
    public function validate_user_exists(int $user_id): bool {
        $user = get_userdata($user_id);
        return $user !== false;
    }
    
    /**
     * Validate post ID exists
     *
     * @param int $post_id Post ID
     * @return bool True if valid, false otherwise
     */
    public function validate_post_exists(int $post_id): bool {
        $post = get_post($post_id);
        return $post !== null;
    }
    
    /**
     * Get validation errors
     *
     * @return array Array of validation errors
     */
    public function get_errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error message
     *
     * @return string|null First error message or null if no errors
     */
    public function get_first_error(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        
        return reset($this->errors);
    }
    
    /**
     * Check if has errors
     *
     * @return bool True if has errors, false otherwise
     */
    public function has_errors(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Clear all errors
     *
     * @return void
     */
    public function clear_errors(): void {
        $this->errors = [];
    }
    
    /**
     * Add custom error
     *
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    public function add_error(string $field, string $message): void {
        $this->errors[$field] = $message;
    }
    
    /**
     * Validate multiple fields with custom rules
     *
     * @param array $data   Data to validate
     * @param array $rules  Validation rules
     * @return bool True if valid, false otherwise
     */
    public function validate(array $data, array $rules): bool {
        $this->errors = [];
        
        foreach ($rules as $field => $rule_set) {
            $value = $data[$field] ?? null;
            $rules_array = explode('|', $rule_set);
            
            foreach ($rules_array as $rule) {
                if (!$this->apply_rule($field, $value, $rule)) {
                    break; // Stop on first error for this field
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply validation rule
     *
     * @param string $field Field name
     * @param mixed  $value Field value
     * @param string $rule  Rule to apply
     * @return bool True if valid, false otherwise
     */
    private function apply_rule(string $field, $value, string $rule): bool {
        // Parse rule (e.g., "min:10" or "max:200")
        $parts = explode(':', $rule);
        $rule_name = $parts[0];
        $rule_param = $parts[1] ?? null;
        
        switch ($rule_name) {
            case 'required':
                if (empty($value)) {
                    $this->errors[$field] = sprintf(__('%s is required.', 'penalis-emailer'), ucfirst($field));
                    return false;
                }
                break;
                
            case 'min':
                if (mb_strlen($value) < (int)$rule_param) {
                    $this->errors[$field] = sprintf(
                        __('%s must be at least %d characters.', 'penalis-emailer'),
                        ucfirst($field),
                        $rule_param
                    );
                    return false;
                }
                break;
                
            case 'max':
                if (mb_strlen($value) > (int)$rule_param) {
                    $this->errors[$field] = sprintf(
                        __('%s must not exceed %d characters.', 'penalis-emailer'),
                        ucfirst($field),
                        $rule_param
                    );
                    return false;
                }
                break;
                
            case 'email':
                if (!is_email($value)) {
                    $this->errors[$field] = sprintf(__('%s must be a valid email address.', 'penalis-emailer'), ucfirst($field));
                    return false;
                }
                break;
                
            case 'array':
                if (!is_array($value)) {
                    $this->errors[$field] = sprintf(__('%s must be an array.', 'penalis-emailer'), ucfirst($field));
                    return false;
                }
                break;
                
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->errors[$field] = sprintf(__('%s must be numeric.', 'penalis-emailer'), ucfirst($field));
                    return false;
                }
                break;
        }
        
        return true;
    }
}

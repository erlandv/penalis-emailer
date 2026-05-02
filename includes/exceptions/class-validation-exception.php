<?php
/**
 * Validation Exception Class
 *
 * Exception thrown when validation fails.
 * Contains validation errors and field information.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Validation_Exception
 *
 * Thrown when data validation fails.
 */
class Penalis_Validation_Exception extends Penalis_Exception {
    
    /**
     * Validation errors
     *
     * @var array
     */
    protected $errors = [];
    
    /**
     * Constructor
     *
     * @param string     $message  Exception message
     * @param array      $errors   Validation errors
     * @param array      $context  Additional context
     * @param \Throwable $previous Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $this->errors = $errors;
        $context['errors'] = $errors;
        
        parent::__construct(
            $message,
            self::get_error_code('VALIDATION_ERROR'),
            $context,
            $previous
        );
    }
    
    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    public function get_errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error
     *
     * @return string|null First error message
     */
    public function get_first_error(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        
        return reset($this->errors);
    }
    
    /**
     * Check if has specific field error
     *
     * @param string $field Field name
     * @return bool True if field has error
     */
    public function has_error(string $field): bool {
        return isset($this->errors[$field]);
    }
    
    /**
     * Get error for specific field
     *
     * @param string $field Field name
     * @return string|null Error message
     */
    public function get_error(string $field): ?string {
        return $this->errors[$field] ?? null;
    }
}

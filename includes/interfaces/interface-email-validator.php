<?php
/**
 * Email Validator Interface
 *
 * Contract for email validation functionality.
 * Defines methods for validating email data.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Email_Validator_Interface
 *
 * Defines the contract for email validation services.
 */
interface Penalis_Email_Validator_Interface {
    
    /**
     * Validate manual email data
     *
     * @param array $data Email data to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_manual_email(array $data): bool;
    
    /**
     * Validate template data
     *
     * @param array $data Template data to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_template(array $data): bool;
    
    /**
     * Get validation errors
     *
     * @return array Array of validation errors
     */
    public function get_errors(): array;
    
    /**
     * Get first error message
     *
     * @return string|null First error message or null if no errors
     */
    public function get_first_error(): ?string;
    
    /**
     * Check if has errors
     *
     * @return bool True if has errors, false otherwise
     */
    public function has_errors(): bool;
    
    /**
     * Clear all errors
     *
     * @return void
     */
    public function clear_errors(): void;
}

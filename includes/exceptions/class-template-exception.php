<?php
/**
 * Template Exception Class
 *
 * Exception thrown when template processing fails.
 * Contains template and placeholder information.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Template_Exception
 *
 * Thrown when template processing fails.
 */
class Penalis_Template_Exception extends Penalis_Exception {
    
    /**
     * Constructor
     *
     * @param string     $message  Exception message
     * @param array      $context  Additional context (template, placeholders, etc.)
     * @param \Throwable $previous Previous exception
     */
    public function __construct(
        string $message = 'Template processing failed',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            self::get_error_code('TEMPLATE_ERROR'),
            $context,
            $previous
        );
    }
    
    /**
     * Get template name
     *
     * @return string|null Template name
     */
    public function get_template_name(): ?string {
        return $this->context['template'] ?? null;
    }
    
    /**
     * Get missing placeholders
     *
     * @return array Missing placeholder names
     */
    public function get_missing_placeholders(): array {
        return $this->context['missing_placeholders'] ?? [];
    }
}

<?php
/**
 * Container Exception Class
 *
 * Exception thrown when service container operations fail.
 * Contains class and dependency information.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Container_Exception
 *
 * Thrown when service container operations fail.
 */
class Penalis_Container_Exception extends Penalis_Exception {
    
    /**
     * Constructor
     *
     * @param string     $message  Exception message
     * @param array      $context  Additional context (class, dependencies, etc.)
     * @param \Throwable $previous Previous exception
     */
    public function __construct(
        string $message = 'Container operation failed',
        array $context = [],
        \Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            self::get_error_code('CONTAINER_ERROR'),
            $context,
            $previous
        );
    }
    
    /**
     * Get class name that failed to resolve
     *
     * @return string|null Class name
     */
    public function get_class_name(): ?string {
        return $this->context['class'] ?? null;
    }
    
    /**
     * Get missing dependencies
     *
     * @return array Missing dependency names
     */
    public function get_missing_dependencies(): array {
        return $this->context['missing_dependencies'] ?? [];
    }
}

<?php
/**
 * Repository Exception Class
 *
 * Exception thrown when repository operations fail.
 * Contains operation and data information.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Repository_Exception
 *
 * Thrown when repository operations fail.
 */
class Penalis_Repository_Exception extends Penalis_Exception {
    
    /**
     * Constructor
     *
     * @param string     $message  Exception message
     * @param array      $context  Additional context (operation, data, etc.)
     * @param \Throwable $previous Previous exception
     */
    public function __construct(
        string $message = 'Repository operation failed',
        array $context = [],
        \Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            self::get_error_code('REPOSITORY_ERROR'),
            $context,
            $previous
        );
    }
    
    /**
     * Get operation name
     *
     * @return string|null Operation name (save, get, delete, etc.)
     */
    public function get_operation(): ?string {
        return $this->context['operation'] ?? null;
    }
    
    /**
     * Get repository name
     *
     * @return string|null Repository class name
     */
    public function get_repository(): ?string {
        return $this->context['repository'] ?? null;
    }
}

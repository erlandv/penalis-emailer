<?php
/**
 * Base Penalis Exception Class
 *
 * Base exception class for all Penalis Emailer exceptions.
 * Provides context and structured error handling.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Exception
 *
 * Base exception with context support for better debugging.
 */
class Penalis_Exception extends Exception {
    
    /**
     * Exception context data
     *
     * @var array
     */
    protected $context = [];
    
    /**
     * Error code mapping
     *
     * @var array
     */
    protected static $error_codes = [
        'VALIDATION_ERROR' => 1001,
        'EMAIL_SEND_ERROR' => 2001,
        'TEMPLATE_ERROR' => 3001,
        'REPOSITORY_ERROR' => 4001,
        'LOGGER_ERROR' => 5001,
        'PARSER_ERROR' => 6001,
        'CONTAINER_ERROR' => 7001,
    ];
    
    /**
     * Constructor
     *
     * @param string     $message  Exception message
     * @param int        $code     Exception code
     * @param array      $context  Additional context data
     * @param \Throwable $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    /**
     * Get exception context
     *
     * @return array Context data
     */
    public function get_context(): array {
        return $this->context;
    }
    
    /**
     * Set exception context
     *
     * @param array $context Context data
     * @return self
     */
    public function set_context(array $context): self {
        $this->context = $context;
        return $this;
    }
    
    /**
     * Add context item
     *
     * @param string $key   Context key
     * @param mixed  $value Context value
     * @return self
     */
    public function add_context(string $key, $value): self {
        $this->context[$key] = $value;
        return $this;
    }
    
    /**
     * Get error code by name
     *
     * @param string $name Error code name
     * @return int Error code
     */
    public static function get_error_code(string $name): int {
        return self::$error_codes[$name] ?? 0;
    }
    
    /**
     * Convert exception to array
     *
     * @return array Exception data
     */
    public function to_array(): array {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString(),
        ];
    }
    
    /**
     * Convert exception to JSON
     *
     * @return string JSON representation
     */
    public function to_json(): string {
        return json_encode($this->to_array(), JSON_PRETTY_PRINT);
    }
    
    /**
     * Log exception
     *
     * @param string $level Log level (error, warning, info)
     * @return void
     */
    public function log(string $level = 'error'): void {
        if (function_exists('error_log')) {
            $log_message = sprintf(
                '[%s] %s in %s:%d - Context: %s',
                strtoupper($level),
                $this->getMessage(),
                $this->getFile(),
                $this->getLine(),
                json_encode($this->context)
            );
            error_log($log_message);
        }
    }
}

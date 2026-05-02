<?php
/**
 * Email Send Exception Class
 *
 * Exception thrown when email sending fails.
 * Contains recipient and email details.
 *
 * @package Penalis_Emailer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Send_Exception
 *
 * Thrown when email sending fails.
 */
class Penalis_Email_Send_Exception extends Penalis_Exception {
    
    /**
     * Failed recipients
     *
     * @var array
     */
    protected $failed_recipients = [];
    
    /**
     * Constructor
     *
     * @param string     $message            Exception message
     * @param array      $failed_recipients  Failed recipient emails
     * @param array      $context            Additional context
     * @param \Throwable $previous           Previous exception
     */
    public function __construct(
        string $message = 'Email send failed',
        array $failed_recipients = [],
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $this->failed_recipients = $failed_recipients;
        $context['failed_recipients'] = $failed_recipients;
        $context['failed_count'] = count($failed_recipients);
        
        parent::__construct(
            $message,
            self::get_error_code('EMAIL_SEND_ERROR'),
            $context,
            $previous
        );
    }
    
    /**
     * Get failed recipients
     *
     * @return array Failed recipient emails
     */
    public function get_failed_recipients(): array {
        return $this->failed_recipients;
    }
    
    /**
     * Get failed count
     *
     * @return int Number of failed recipients
     */
    public function get_failed_count(): int {
        return count($this->failed_recipients);
    }
    
    /**
     * Check if specific recipient failed
     *
     * @param string $email Recipient email
     * @return bool True if recipient failed
     */
    public function has_failed_recipient(string $email): bool {
        return in_array($email, $this->failed_recipients, true);
    }
}

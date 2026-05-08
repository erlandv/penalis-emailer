<?php
/**
 * Email Queue Processor
 *
 * Processes the email sending queue in batches via WP-Cron.
 * Each cron run picks up a configurable number of pending items,
 * sends them, and schedules the next run if more items remain.
 *
 * @package Penalis_Emailer
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Queue_Processor
 *
 * Handles background processing of the email queue.
 */
class Penalis_Email_Queue_Processor {

    /**
     * Queue repository
     *
     * @var Penalis_Email_Queue_Repository
     */
    private $queue;

    /**
     * Email template
     *
     * @var Penalis_Email_Template
     */
    private $template;

    /**
     * Email logger
     *
     * @var Penalis_Email_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Penalis_Email_Queue_Repository $queue    Queue repository
     * @param Penalis_Email_Template         $template Email template
     * @param Penalis_Email_Logger           $logger   Email logger
     */
    public function __construct(
        Penalis_Email_Queue_Repository $queue,
        Penalis_Email_Template $template,
        Penalis_Email_Logger $logger
    ) {
        $this->queue    = $queue;
        $this->template = $template;
        $this->logger   = $logger;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Process one batch of pending queue items.
     *
     * Called by WP-Cron via the penalis_process_email_queue hook.
     * After processing, schedules the next run if more items remain.
     *
     * @return array {
     *     @type int $processed Total items attempted
     *     @type int $sent      Successfully sent
     *     @type int $failed    Failed (will retry)
     * }
     */
    public function process_batch(): array {
        $batch_size = Penalis_Config::get_queue_batch_size();
        $items      = $this->queue->get_pending_batch($batch_size);

        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        if (empty($items)) {
            return $stats;
        }

        // Group items by job_id so we can log per-job after the batch
        $jobs_in_batch = [];

        foreach ($items as $item) {
            $stats['processed']++;

            // Mark as processing to prevent double-processing on overlapping cron runs
            $this->queue->mark_processing($item['id']);

            $sent = $this->send_item($item);

            if ($sent) {
                $this->queue->mark_sent($item['id']);
                $stats['sent']++;
            } else {
                $this->queue->mark_failed(
                    $item['id'],
                    $this->get_last_wp_mail_error(),
                    $item['attempts']
                );
                $stats['failed']++;
            }

            $jobs_in_batch[$item['job_id']] = true;
        }

        // After the batch, check each job to see if it just completed
        // and write a consolidated log entry if so
        foreach (array_keys($jobs_in_batch) as $job_id) {
            $this->maybe_log_completed_job($job_id);
        }

        // Schedule next batch if more items are waiting
        $this->maybe_schedule_next_run();

        return $stats;
    }

    /**
     * Schedule the first cron run for a newly enqueued job.
     *
     * Should be called immediately after bulk_enqueue().
     *
     * @return void
     */
    public function schedule_next_run(): void {
        if (!wp_next_scheduled(Penalis_Config::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, Penalis_Config::CRON_HOOK);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Send a single queue item to its recipient.
     *
     * @param array $item Queue row
     * @return bool
     */
    private function send_item(array $item): bool {
        $user = get_userdata($item['user_id']);

        if (!$user || !is_email($user->user_email)) {
            return false;
        }

        // Prepare personalisation data
        $user_data = [
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'user_login'   => $user->user_login,
        ];

        // Render HTML email
        $preheader  = mb_substr(wp_strip_all_tags($item['subject']), 0, 100);
        $email_html = $this->template->render_flexible_email($item['body'], $user_data, $preheader);

        // Build headers
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_name   = !empty($item['from_name']) ? $item['from_name'] : Penalis_Config::DEFAULT_FROM_NAME;
        $headers     = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <no-reply@' . $site_domain . '>',
        ];

        // Apply same filters as the synchronous sender for consistency
        $subject = apply_filters('penalis_email_subject', $item['subject'], 0);
        $body    = apply_filters('penalis_email_message', $email_html, 0);
        $headers = apply_filters('penalis_email_headers', $headers, 0);

        // Set HTML content type
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
        $sent = wp_mail($user->user_email, $subject, $body, $headers);
        remove_filter('wp_mail_content_type', [$this, 'set_html_content_type']);

        return (bool) $sent;
    }

    /**
     * Check if a job is fully complete and, if so, write a log entry.
     *
     * A job is complete when it has no more pending/processing/failed items.
     *
     * @param string $job_id
     * @return void
     */
    private function maybe_log_completed_job(string $job_id): void {
        $summary = $this->queue->get_job_summary($job_id);

        if ($summary['overall'] !== 'completed') {
            return;
        }

        // Avoid duplicate log entries — check if a log for this job_id already exists
        $repository = $this->logger->get_repository();
        $existing   = $this->find_log_by_job_id($job_id);
        if ($existing) {
            return;
        }

        // Collect recipient IDs from the queue for this job
        // We query the queue table directly since items are still there (just status=sent)
        global $wpdb;
        $queue_table = Penalis_Database::get_queue_table();
        $recipients  = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$queue_table} WHERE job_id = %s AND status = 'sent'",
                $job_id
            )
        );
        $recipients = array_map('intval', (array) $recipients);

        // Retrieve subject/body from the first sent item
        $first_item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT subject, body FROM {$queue_table} WHERE job_id = %s LIMIT 1",
                $job_id
            ),
            ARRAY_A
        );

        $subject = $first_item['subject'] ?? '';
        $body    = $first_item['body']    ?? '';

        $this->logger->log_manual_email($recipients, $subject, $body, $job_id);
    }

    /**
     * Find a log entry by job_id.
     *
     * @param string $job_id
     * @return array|null
     */
    private function find_log_by_job_id(string $job_id): ?array {
        global $wpdb;
        $log_table = Penalis_Database::get_log_table();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$log_table} WHERE job_id = %s LIMIT 1",
                $job_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Schedule the next cron run if there are still pending items.
     *
     * @return void
     */
    private function maybe_schedule_next_run(): void {
        if (!$this->queue->has_pending()) {
            return;
        }

        if (!wp_next_scheduled(Penalis_Config::CRON_HOOK)) {
            $interval = Penalis_Config::get_queue_interval();
            wp_schedule_single_event(time() + $interval, Penalis_Config::CRON_HOOK);
        }
    }

    /**
     * Get the last wp_mail error message, if any.
     *
     * @return string
     */
    private function get_last_wp_mail_error(): string {
        global $phpmailer;

        if (isset($phpmailer) && $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer) {
            return $phpmailer->ErrorInfo ?: 'wp_mail returned false';
        }

        return 'wp_mail returned false';
    }

    /**
     * Set email content type to HTML.
     * Used as a temporary filter around wp_mail calls.
     *
     * @return string
     */
    public function set_html_content_type(): string {
        return 'text/html';
    }
}

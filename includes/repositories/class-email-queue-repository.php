<?php
/**
 * Email Queue Repository
 *
 * Manages the email sending queue in a dedicated custom database table.
 * Each row represents one email to be sent to one recipient.
 *
 * @package Penalis_Emailer
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Queue_Repository
 *
 * Provides CRUD operations for the email queue table.
 */
class Penalis_Email_Queue_Repository {

    /**
     * Queue table name
     *
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Penalis_Database::get_queue_table();
    }

    // =========================================================================
    // ENQUEUE
    // =========================================================================

    /**
     * Add a single recipient job to the queue.
     *
     * @param array $data {
     *     @type string $job_id    Batch job identifier
     *     @type int    $user_id   WordPress user ID
     *     @type string $subject   Email subject
     *     @type string $body      Email body (markdown/HTML)
     *     @type string $from_name Sender display name
     * }
     * @return bool
     */
    public function enqueue(array $data): bool {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'job_id'       => $data['job_id']    ?? '',
                'user_id'      => $data['user_id']   ?? 0,
                'subject'      => $data['subject']   ?? '',
                'body'         => $data['body']      ?? '',
                'from_name'    => $data['from_name'] ?? '',
                'status'       => 'pending',
                'attempts'     => 0,
                'next_attempt' => time(),
                'created_at'   => time(),
                'sent_at'      => 0,
            ],
            ['%s','%d','%s','%s','%s','%s','%d','%d','%d','%d']
        );

        return $result !== false;
    }

    /**
     * Bulk-enqueue multiple recipients for the same job.
     * Uses a single multi-row INSERT for efficiency.
     *
     * @param string $job_id    Batch job identifier
     * @param array  $user_ids  Array of WordPress user IDs
     * @param string $subject   Email subject
     * @param string $body      Email body
     * @param string $from_name Sender display name
     * @return int Number of rows inserted
     */
    public function bulk_enqueue(string $job_id, array $user_ids, string $subject, string $body, string $from_name): int {
        global $wpdb;

        if (empty($user_ids)) {
            return 0;
        }

        $now          = time();
        $placeholders = [];
        $values       = [];

        foreach ($user_ids as $user_id) {
            $placeholders[] = '(%s, %d, %s, %s, %s, %s, %d, %d, %d, %d)';
            $values[]       = $job_id;
            $values[]       = (int) $user_id;
            $values[]       = $subject;
            $values[]       = $body;
            $values[]       = $from_name;
            $values[]       = 'pending';
            $values[]       = 0;       // attempts
            $values[]       = $now;    // next_attempt
            $values[]       = $now;    // created_at
            $values[]       = 0;       // sent_at
        }

        $placeholder_string = implode(', ', $placeholders);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "INSERT INTO {$this->table}
             (job_id, user_id, subject, body, from_name, status, attempts, next_attempt, created_at, sent_at)
             VALUES {$placeholder_string}",
            ...$values
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query($sql);

        return (int) $result;
    }

    // =========================================================================
    // FETCH FOR PROCESSING
    // =========================================================================

    /**
     * Get a batch of pending (or retry-eligible) queue items.
     *
     * Only returns items where next_attempt <= now, so retry backoff is respected.
     *
     * @param int $limit Maximum number of items to fetch
     * @return array Array of queue row arrays
     */
    public function get_pending_batch(int $limit = 30): array {
        global $wpdb;

        $now = time();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE status IN ('pending', 'failed')
                   AND next_attempt <= %d
                 ORDER BY created_at ASC
                 LIMIT %d",
                $now,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_map([$this, 'cast_row'], $rows) : [];
    }

    /**
     * Check whether there are any pending or retry-eligible items.
     *
     * @return bool
     */
    public function has_pending(): bool {
        global $wpdb;

        $now   = time();
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE status IN ('pending', 'failed')
                   AND next_attempt <= %d",
                $now
            )
        );

        return (int) $count > 0;
    }

    /**
     * Count pending items regardless of next_attempt time.
     * Used for progress reporting.
     *
     * @param string $job_id Optional — filter by job
     * @return int
     */
    public function count_pending(string $job_id = ''): int {
        global $wpdb;

        if ($job_id !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table}
                     WHERE job_id = %s AND status IN ('pending', 'failed')",
                    $job_id
                )
            );
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE status IN ('pending', 'failed')"
        );
    }

    // =========================================================================
    // STATUS UPDATES
    // =========================================================================

    /**
     * Mark a queue item as "processing" to prevent double-processing.
     *
     * @param int $id Row primary key
     * @return bool
     */
    public function mark_processing(int $id): bool {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table,
            ['status' => 'processing'],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Mark a queue item as successfully sent.
     *
     * @param int $id Row primary key
     * @return bool
     */
    public function mark_sent(int $id): bool {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table,
            [
                'status'  => 'sent',
                'sent_at' => time(),
            ],
            ['id' => $id],
            ['%s', '%d'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Mark a queue item as failed and schedule a retry.
     *
     * Retry schedule (exponential backoff):
     *   attempt 1 → retry after  5 minutes
     *   attempt 2 → retry after 15 minutes
     *   attempt 3 → retry after 60 minutes
     *   attempt 4+ → permanently failed (status = 'permanently_failed')
     *
     * @param int    $id            Row primary key
     * @param string $error_message Error description
     * @param int    $attempts      Current attempt count (before this failure)
     * @return bool
     */
    public function mark_failed(int $id, string $error_message, int $attempts): bool {
        global $wpdb;

        $new_attempts = $attempts + 1;
        $max_attempts = (int) get_option('penalis_queue_max_attempts', 3);

        if ($new_attempts >= $max_attempts) {
            // Permanently failed — no more retries
            $updated = $wpdb->update(
                $this->table,
                [
                    'status'        => 'permanently_failed',
                    'attempts'      => $new_attempts,
                    'error_message' => $error_message,
                ],
                ['id' => $id],
                ['%s', '%d', '%s'],
                ['%d']
            );
        } else {
            // Schedule retry with exponential backoff
            $backoff_minutes = [5, 15, 60];
            $delay_seconds   = ($backoff_minutes[$new_attempts - 1] ?? 60) * 60;

            $updated = $wpdb->update(
                $this->table,
                [
                    'status'        => 'failed',
                    'attempts'      => $new_attempts,
                    'next_attempt'  => time() + $delay_seconds,
                    'error_message' => $error_message,
                ],
                ['id' => $id],
                ['%s', '%d', '%d', '%s'],
                ['%d']
            );
        }

        return $updated !== false;
    }

    // =========================================================================
    // JOB STATUS / PROGRESS
    // =========================================================================

    /**
     * Get a summary of all statuses for a given job_id.
     * Used for progress tracking on the frontend.
     *
     * @param string $job_id
     * @return array {
     *     @type int $total
     *     @type int $pending
     *     @type int $processing
     *     @type int $sent
     *     @type int $failed
     *     @type int $permanently_failed
     * }
     */
    public function get_job_summary(string $job_id): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as cnt
                 FROM {$this->table}
                 WHERE job_id = %s
                 GROUP BY status",
                $job_id
            ),
            ARRAY_A
        );

        $summary = [
            'total'               => 0,
            'pending'             => 0,
            'processing'          => 0,
            'sent'                => 0,
            'failed'              => 0,
            'permanently_failed'  => 0,
        ];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $status = $row['status'];
                $count  = (int) $row['cnt'];
                if (array_key_exists($status, $summary)) {
                    $summary[$status] = $count;
                }
                $summary['total'] += $count;
            }
        }

        // Derive a human-readable overall status
        if ($summary['total'] === 0) {
            $summary['overall'] = 'not_found';
        } elseif ($summary['pending'] > 0 || $summary['processing'] > 0 || $summary['failed'] > 0) {
            $summary['overall'] = 'in_progress';
        } else {
            $summary['overall'] = 'completed';
        }

        return $summary;
    }

    /**
     * Get all distinct job IDs that still have pending/processing items.
     *
     * @return array
     */
    public function get_active_job_ids(): array {
        global $wpdb;

        $rows = $wpdb->get_col(
            "SELECT DISTINCT job_id FROM {$this->table}
             WHERE status IN ('pending', 'processing', 'failed')"
        );

        return is_array($rows) ? $rows : [];
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

    /**
     * Delete all sent/permanently_failed items older than $days days.
     *
     * @param int $days
     * @return int Number of rows deleted
     */
    public function cleanup_old(int $days = 30): int {
        global $wpdb;

        $cutoff  = time() - ($days * DAY_IN_SECONDS);
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table}
                 WHERE status IN ('sent', 'permanently_failed')
                   AND created_at < %d",
                $cutoff
            )
        );

        return (int) $deleted;
    }

    /**
     * Delete all queue items for a specific job.
     *
     * @param string $job_id
     * @return int
     */
    public function delete_job(string $job_id): int {
        global $wpdb;

        $deleted = $wpdb->delete($this->table, ['job_id' => $job_id], ['%s']);

        return (int) $deleted;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Cast raw DB row values to correct PHP types.
     *
     * @param array $row
     * @return array
     */
    private function cast_row(array $row): array {
        $row['id']           = (int) $row['id'];
        $row['user_id']      = (int) $row['user_id'];
        $row['attempts']     = (int) $row['attempts'];
        $row['next_attempt'] = (int) $row['next_attempt'];
        $row['created_at']   = (int) $row['created_at'];
        $row['sent_at']      = (int) $row['sent_at'];

        return $row;
    }
}

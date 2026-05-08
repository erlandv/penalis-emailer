<?php
/**
 * Email Log DB Repository
 *
 * Custom database table implementation of the email log repository interface.
 * Replaces the wp_options-based implementation for better scalability.
 *
 * @package Penalis_Emailer
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Log_DB_Repository
 *
 * Stores email logs in a dedicated custom database table.
 * Implements the same interface as the legacy options repository,
 * so no changes are required in the layer above.
 */
class Penalis_Email_Log_DB_Repository implements Penalis_Email_Log_Repository_Interface {

    /**
     * Log table name
     *
     * @var string
     */
    private $log_table;

    /**
     * Draft table name
     *
     * @var string
     */
    private $draft_table;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_table   = Penalis_Database::get_log_table();
        $this->draft_table = Penalis_Database::get_draft_table();
    }

    // =========================================================================
    // LOG METHODS
    // =========================================================================

    /**
     * Save a log entry.
     *
     * @param array $log_entry Log entry data
     * @return bool
     */
    public function save(array $log_entry): bool {
        global $wpdb;

        $log_key = $log_entry['id'] ?? ('log_' . time() . '_' . wp_generate_password(8, false));

        // Guard against duplicate keys
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->log_table} WHERE log_key = %s", $log_key)
        );
        if ($exists) {
            return true; // Already saved — treat as success
        }

        $recipients = $log_entry['recipients'] ?? [];

        $result = $wpdb->insert(
            $this->log_table,
            [
                'log_key'         => $log_key,
                'type'            => $log_entry['type']            ?? 'manual',
                'subject'         => $log_entry['subject']         ?? '',
                'body_preview'    => $log_entry['body_preview']    ?? '',
                'job_id'          => $log_entry['job_id']          ?? '',
                'post_id'         => $log_entry['post_id']         ?? 0,
                'post_title'      => $log_entry['post_title']      ?? '',
                'post_url'        => $log_entry['post_url']        ?? '',
                'recipient_count' => $log_entry['recipient_count'] ?? count($recipients),
                'recipients'      => wp_json_encode($recipients),
                'recipient_email' => $log_entry['recipient_email'] ?? '',
                'recipient_name'  => $log_entry['recipient_name']  ?? '',
                'sent_by'         => $log_entry['sent_by']         ?? 0,
                'sent_at'         => $log_entry['sent_at']         ?? time(),
                'status'          => $log_entry['status']          ?? 'sent',
            ],
            ['%s','%s','%s','%s','%s','%d','%s','%s','%d','%s','%s','%s','%d','%d','%s']
        );

        return $result !== false;
    }

    /**
     * Get all log entries, sorted by sent_at descending.
     *
     * @param int $limit 0 = no limit
     * @return array
     */
    public function get_all(int $limit = 0): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->log_table} ORDER BY sent_at DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'decode_row'], $rows);
    }

    /**
     * Find a log entry by its string key (log_key).
     *
     * @param string $id
     * @return array|null
     */
    public function find_by_id(string $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->log_table} WHERE log_key = %s", $id),
            ARRAY_A
        );

        return $row ? $this->decode_row($row) : null;
    }

    /**
     * Delete a single log entry by its string key.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool {
        global $wpdb;

        $deleted = $wpdb->delete($this->log_table, ['log_key' => $id], ['%s']);

        return $deleted !== false && $deleted > 0;
    }

    /**
     * Delete multiple log entries by their string keys.
     *
     * @param array $ids
     * @return int Number deleted
     */
    public function delete_multiple(array $ids): int {
        global $wpdb;

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->log_table} WHERE log_key IN ({$placeholders})",
                ...$ids
            )
        );

        return (int) $deleted;
    }

    /**
     * Delete all log entries of a specific type.
     *
     * @param string $type 'all', 'manual', or 'automatic'
     * @return int Number deleted
     */
    public function delete_by_type(string $type = 'all'): int {
        global $wpdb;

        if ($type === 'all') {
            $deleted = $wpdb->query("TRUNCATE TABLE {$this->log_table}");
            // TRUNCATE returns 0 on success in wpdb — use count before
            return (int) $deleted;
        }

        $deleted = $wpdb->delete($this->log_table, ['type' => $type], ['%s']);

        return (int) $deleted;
    }

    /**
     * Delete old entries, keeping only the most recent $keep_count.
     *
     * @param int $keep_count
     * @return int Number deleted
     */
    public function cleanup(int $keep_count): int {
        global $wpdb;

        $total = $this->count();

        if ($total <= $keep_count) {
            return 0;
        }

        // Find the sent_at of the Nth newest entry
        $cutoff = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT sent_at FROM {$this->log_table} ORDER BY sent_at DESC LIMIT 1 OFFSET %d",
                $keep_count
            )
        );

        if ($cutoff === null) {
            return 0;
        }

        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->log_table} WHERE sent_at <= %d", $cutoff)
        );

        return (int) $deleted;
    }

    /**
     * Count total log entries.
     *
     * @return int
     */
    public function count(): int {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
    }

    // =========================================================================
    // DRAFT METHODS
    // =========================================================================

    /**
     * Save a new draft entry.
     *
     * @param array $draft_data
     * @return bool
     */
    public function save_draft(array $draft_data): bool {
        global $wpdb;

        $draft_key = $draft_data['id'] ?? ('draft_' . time() . '_' . wp_generate_password(8, false));
        $recipients = $draft_data['recipients'] ?? [];
        $now = time();

        $result = $wpdb->insert(
            $this->draft_table,
            [
                'draft_key'       => $draft_key,
                'from_name'       => $draft_data['from_name']       ?? '',
                'subject'         => $draft_data['subject']         ?? '',
                'body'            => $draft_data['body']            ?? '',
                'recipient_count' => $draft_data['recipient_count'] ?? count($recipients),
                'recipients'      => wp_json_encode($recipients),
                'created_by'      => $draft_data['created_by']      ?? get_current_user_id(),
                'updated_by'      => get_current_user_id(),
                'created_at'      => $draft_data['created_at']      ?? $now,
                'updated_at'      => $now,
            ],
            ['%s','%s','%s','%s','%d','%s','%d','%d','%d','%d']
        );

        return $result !== false;
    }

    /**
     * Get all draft entries, sorted by updated_at descending.
     *
     * @param int $limit 0 = no limit
     * @return array
     */
    public function get_drafts(int $limit = 0): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->draft_table} ORDER BY updated_at DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'decode_draft_row'], $rows);
    }

    /**
     * Find a draft by its string key.
     *
     * @param string $id
     * @return array|null
     */
    public function find_draft_by_id(string $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->draft_table} WHERE draft_key = %s", $id),
            ARRAY_A
        );

        return $row ? $this->decode_draft_row($row) : null;
    }

    /**
     * Update an existing draft.
     *
     * @param string $id
     * @param array  $draft_data
     * @return bool
     */
    public function update_draft(string $id, array $draft_data): bool {
        global $wpdb;

        $recipients = $draft_data['recipients'] ?? [];

        $updated = $wpdb->update(
            $this->draft_table,
            [
                'from_name'       => $draft_data['from_name']       ?? '',
                'subject'         => $draft_data['subject']         ?? '',
                'body'            => $draft_data['body']            ?? '',
                'recipient_count' => $draft_data['recipient_count'] ?? count($recipients),
                'recipients'      => wp_json_encode($recipients),
                'updated_by'      => get_current_user_id(),
                'updated_at'      => time(),
            ],
            ['draft_key' => $id],
            ['%s','%s','%s','%d','%s','%d','%d'],
            ['%s']
        );

        return $updated !== false;
    }

    /**
     * Delete a draft by its string key.
     *
     * @param string $id
     * @return bool
     */
    public function delete_draft(string $id): bool {
        global $wpdb;

        $deleted = $wpdb->delete($this->draft_table, ['draft_key' => $id], ['%s']);

        return $deleted !== false && $deleted > 0;
    }

    /**
     * Convert a draft to sent status.
     *
     * For the DB implementation, drafts live in a separate table.
     * "Converting" means deleting the draft — the log entry is created
     * separately by the email sender after successful dispatch.
     *
     * @param string $id
     * @param int    $sent_at
     * @return bool
     */
    public function convert_draft_to_sent(string $id, int $sent_at): bool {
        return $this->delete_draft($id);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Decode a raw DB row from the log table into the expected array shape.
     *
     * Converts JSON-encoded recipients back to a PHP array and casts
     * numeric fields to the correct types.
     *
     * @param array $row Raw DB row
     * @return array
     */
    private function decode_row(array $row): array {
        $row['id']              = $row['log_key'];
        $row['post_id']         = (int) $row['post_id'];
        $row['recipient_count'] = (int) $row['recipient_count'];
        $row['sent_by']         = (int) $row['sent_by'];
        $row['sent_at']         = (int) $row['sent_at'];
        $row['recipients']      = !empty($row['recipients'])
            ? json_decode($row['recipients'], true)
            : [];

        return $row;
    }

    /**
     * Decode a raw DB row from the draft table into the expected array shape.
     *
     * @param array $row Raw DB row
     * @return array
     */
    private function decode_draft_row(array $row): array {
        $row['id']              = $row['draft_key'];
        $row['status']          = 'draft';
        $row['type']            = 'manual';
        $row['recipient_count'] = (int) $row['recipient_count'];
        $row['created_by']      = (int) $row['created_by'];
        $row['updated_by']      = (int) ($row['updated_by'] ?? $row['created_by']);
        $row['created_at']      = (int) $row['created_at'];
        $row['updated_at']      = (int) $row['updated_at'];
        $row['recipients']      = !empty($row['recipients'])
            ? json_decode($row['recipients'], true)
            : [];

        return $row;
    }
}

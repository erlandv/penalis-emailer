<?php
/**
 * Email Log Repository Interface
 *
 * Defines the contract for email log data access.
 * Implementations can use different storage mechanisms (options, custom tables, etc.)
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Email_Log_Repository_Interface
 *
 * Contract for email log storage operations.
 */
interface Penalis_Email_Log_Repository_Interface {
    
    /**
     * Save a log entry
     *
     * @param array $log_entry Log entry data
     * @return bool True on success, false on failure
     */
    public function save(array $log_entry): bool;
    
    /**
     * Get all log entries
     *
     * @param int $limit Optional limit for number of entries (0 = no limit)
     * @return array Array of log entries
     */
    public function get_all(int $limit = 0): array;
    
    /**
     * Find a log entry by ID
     *
     * @param string $id Log entry ID
     * @return array|null Log entry or null if not found
     */
    public function find_by_id(string $id): ?array;
    
    /**
     * Delete a single log entry by ID
     *
     * @param string $id Log entry ID
     * @return bool True on success, false on failure
     */
    public function delete(string $id): bool;
    
    /**
     * Delete multiple log entries by IDs
     *
     * @param array $ids Array of log entry IDs
     * @return int Number of entries deleted
     */
    public function delete_multiple(array $ids): int;
    
    /**
     * Delete all log entries of a specific type
     *
     * @param string $type Type filter: 'all', 'manual', 'automatic'
     * @return int Number of entries deleted
     */
    public function delete_by_type(string $type = 'all'): int;
    
    /**
     * Delete old log entries, keeping only the most recent ones
     *
     * @param int $keep_count Number of entries to keep
     * @return int Number of entries deleted
     */
    public function cleanup(int $keep_count): int;
    
    /**
     * Count total log entries
     *
     * @return int Total number of log entries
     */
    public function count(): int;
}

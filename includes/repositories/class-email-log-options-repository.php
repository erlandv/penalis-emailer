<?php
/**
 * Email Log Options Repository
 *
 * WordPress Options API implementation of email log repository.
 * Stores manual email logs in wp_options table.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Log_Options_Repository
 *
 * Stores email logs using WordPress Options API.
 */
class Penalis_Email_Log_Options_Repository implements Penalis_Email_Log_Repository_Interface {
    
    /**
     * Options key for storing logs
     *
     * @var string
     */
    private $option_key;
    
    /**
     * Constructor
     *
     * @param string $option_key WordPress option key for storing logs
     */
    public function __construct(string $option_key) {
        $this->option_key = $option_key;
    }
    
    /**
     * Save a log entry
     *
     * @param array $log_entry Log entry data
     * @return bool True on success, false on failure
     */
    public function save(array $log_entry): bool {
        $logs = $this->get_all();
        $logs[] = $log_entry;
        return update_option($this->option_key, $logs);
    }
    
    /**
     * Get all log entries
     *
     * @param int $limit Optional limit for number of entries (0 = no limit)
     * @return array Array of log entries, sorted by sent_at descending
     */
    public function get_all(int $limit = 0): array {
        $logs = get_option($this->option_key, []);
        
        // Ensure it's an array
        if (!is_array($logs)) {
            $logs = [];
        }
        
        // Sort by sent_at descending (newest first)
        usort($logs, function($a, $b) {
            $time_a = $this->get_timestamp($a);
            $time_b = $this->get_timestamp($b);
            return $time_b - $time_a;
        });
        
        // Apply limit if specified
        if ($limit > 0) {
            $logs = array_slice($logs, 0, $limit);
        }
        
        return $logs;
    }
    
    /**
     * Find a log entry by ID
     *
     * @param string $id Log entry ID
     * @return array|null Log entry or null if not found
     */
    public function find_by_id(string $id): ?array {
        $logs = $this->get_all();
        
        foreach ($logs as $log) {
            if (isset($log['id']) && $log['id'] === $id) {
                return $log;
            }
        }
        
        return null;
    }
    
    /**
     * Delete old log entries, keeping only the most recent ones
     *
     * @param int $keep_count Number of entries to keep
     * @return int Number of entries deleted
     */
    public function cleanup(int $keep_count): int {
        $logs = $this->get_all();
        
        if (count($logs) <= $keep_count) {
            return 0;
        }
        
        $deleted_count = count($logs) - $keep_count;
        $logs = array_slice($logs, 0, $keep_count);
        
        update_option($this->option_key, $logs);
        
        return $deleted_count;
    }
    
    /**
     * Count total log entries
     *
     * @return int Total number of log entries
     */
    public function count(): int {
        $logs = get_option($this->option_key, []);
        return is_array($logs) ? count($logs) : 0;
    }
    
    /**
     * Get timestamp from log entry (handles both old and new format)
     *
     * @param array $log Log entry
     * @return int Timestamp
     */
    private function get_timestamp(array $log): int {
        return isset($log['sent_at']) ? $log['sent_at'] : (isset($log['timestamp']) ? $log['timestamp'] : 0);
    }
}

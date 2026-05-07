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
     * Delete a single log entry by ID
     *
     * @param string $id Log entry ID
     * @return bool True on success, false on failure
     */
    public function delete(string $id): bool {
        $logs = get_option($this->option_key, []);
        
        if (!is_array($logs)) {
            return false;
        }
        
        $original_count = count($logs);
        
        // Check if this is a legacy ID (format: legacy_timestamp_hash)
        if (strpos($id, 'legacy_') === 0) {
            // Extract timestamp from legacy ID
            $parts = explode('_', $id);
            if (count($parts) >= 3) {
                $timestamp = intval($parts[1]);
                $subject_hash = $parts[2];
                
                // Filter out the entry with matching timestamp and subject hash
                $logs = array_filter($logs, function($log) use ($timestamp, $subject_hash) {
                    $log_time = isset($log['sent_at']) ? $log['sent_at'] : (isset($log['timestamp']) ? $log['timestamp'] : 0);
                    $log_subject = $log['subject'] ?? '';
                    $log_hash = substr(md5($log_subject), 0, 8);
                    
                    // Keep entries that DON'T match
                    return !($log_time == $timestamp && $log_hash === $subject_hash);
                });
            }
        } else {
            // Normal ID - filter by id field
            $logs = array_filter($logs, function($log) use ($id) {
                return !isset($log['id']) || $log['id'] !== $id;
            });
        }
        
        // Re-index array
        $logs = array_values($logs);
        
        // Save back
        $result = update_option($this->option_key, $logs);
        
        // Return true if count changed and update succeeded
        return $result && count($logs) < $original_count;
    }
    
    /**
     * Delete multiple log entries by IDs
     *
     * @param array $ids Array of log entry IDs
     * @return int Number of entries deleted
     */
    public function delete_multiple(array $ids): int {
        $logs = get_option($this->option_key, []);
        
        if (!is_array($logs)) {
            return 0;
        }
        
        $original_count = count($logs);
        
        // Separate legacy IDs from normal IDs
        $legacy_ids = [];
        $normal_ids = [];
        
        foreach ($ids as $id) {
            if (strpos($id, 'legacy_') === 0) {
                // Parse legacy ID
                $parts = explode('_', $id);
                if (count($parts) >= 3) {
                    $legacy_ids[] = [
                        'timestamp' => intval($parts[1]),
                        'hash' => $parts[2]
                    ];
                }
            } else {
                $normal_ids[] = $id;
            }
        }
        
        // Filter out entries
        $logs = array_filter($logs, function($log) use ($normal_ids, $legacy_ids) {
            // Check normal ID
            if (isset($log['id']) && in_array($log['id'], $normal_ids)) {
                return false; // Remove this entry
            }
            
            // Check legacy ID
            $log_time = isset($log['sent_at']) ? $log['sent_at'] : (isset($log['timestamp']) ? $log['timestamp'] : 0);
            $log_subject = $log['subject'] ?? '';
            $log_hash = substr(md5($log_subject), 0, 8);
            
            foreach ($legacy_ids as $legacy) {
                if ($log_time == $legacy['timestamp'] && $log_hash === $legacy['hash']) {
                    return false; // Remove this entry
                }
            }
            
            return true; // Keep this entry
        });
        
        // Re-index array
        $logs = array_values($logs);
        
        // Save back
        update_option($this->option_key, $logs);
        
        // Return number deleted
        return $original_count - count($logs);
    }
    
    /**
     * Delete all log entries of a specific type
     *
     * @param string $type Type filter: 'all', 'manual', 'automatic'
     * @return int Number of entries deleted
     */
    public function delete_by_type(string $type = 'all'): int {
        $logs = get_option($this->option_key, []);
        
        if (!is_array($logs)) {
            return 0;
        }
        
        $original_count = count($logs);
        
        if ($type === 'all') {
            // Delete everything
            update_option($this->option_key, []);
            return $original_count;
        }
        
        // Filter by type - keep entries that DON'T match the type to delete
        $logs = array_filter($logs, function($log) use ($type) {
            $log_type = isset($log['type']) ? $log['type'] : 'manual';
            return $log_type !== $type;
        });
        
        // Re-index array
        $logs = array_values($logs);
        
        // Save back
        update_option($this->option_key, $logs);
        
        // Return number deleted
        return $original_count - count($logs);
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
    
    /**
     * Save a draft entry
     *
     * @param array $draft_data Draft data
     * @return bool True on success, false on failure
     */
    public function save_draft(array $draft_data): bool {
        // Generate unique draft ID if not provided
        if (!isset($draft_data['id'])) {
            $draft_data['id'] = 'draft_' . time() . '_' . wp_generate_password(8, false);
        }
        
        // Set draft-specific fields
        $draft_data['status'] = 'draft';
        $draft_data['created_at'] = $draft_data['created_at'] ?? time();
        $draft_data['updated_at'] = time();
        
        // Save to logs (drafts are stored in the same option)
        return $this->save($draft_data);
    }
    
    /**
     * Get all draft entries
     *
     * @param int $limit Optional limit for number of entries (0 = no limit)
     * @return array Array of draft entries, sorted by updated_at descending
     */
    public function get_drafts(int $limit = 0): array {
        $logs = get_option($this->option_key, []);
        
        // Ensure it's an array
        if (!is_array($logs)) {
            $logs = [];
        }
        
        // Filter only drafts
        $drafts = array_filter($logs, function($log) {
            return isset($log['status']) && $log['status'] === 'draft';
        });
        
        // Sort by updated_at descending (newest first)
        usort($drafts, function($a, $b) {
            $time_a = $a['updated_at'] ?? $a['created_at'] ?? 0;
            $time_b = $b['updated_at'] ?? $b['created_at'] ?? 0;
            return $time_b - $time_a;
        });
        
        // Apply limit if specified
        if ($limit > 0) {
            $drafts = array_slice($drafts, 0, $limit);
        }
        
        return $drafts;
    }
    
    /**
     * Find a draft entry by ID
     *
     * @param string $id Draft entry ID
     * @return array|null Draft entry or null if not found
     */
    public function find_draft_by_id(string $id): ?array {
        $logs = get_option($this->option_key, []);
        
        foreach ($logs as $log) {
            if (isset($log['id']) && $log['id'] === $id && isset($log['status']) && $log['status'] === 'draft') {
                return $log;
            }
        }
        
        return null;
    }
    
    /**
     * Update a draft entry
     *
     * @param string $id         Draft entry ID
     * @param array  $draft_data Updated draft data
     * @return bool True on success, false on failure
     */
    public function update_draft(string $id, array $draft_data): bool {
        $logs = get_option($this->option_key, []);
        
        if (!is_array($logs)) {
            return false;
        }
        
        $found = false;
        
        foreach ($logs as $index => $log) {
            if (isset($log['id']) && $log['id'] === $id && isset($log['status']) && $log['status'] === 'draft') {
                // Preserve original ID and created_at
                $draft_data['id'] = $id;
                $draft_data['created_at'] = $log['created_at'] ?? time();
                $draft_data['updated_at'] = time();
                $draft_data['status'] = 'draft';
                
                // Update the entry
                $logs[$index] = $draft_data;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        return update_option($this->option_key, $logs);
    }
    
    /**
     * Delete a draft entry by ID
     *
     * @param string $id Draft entry ID
     * @return bool True on success, false on failure
     */
    public function delete_draft(string $id): bool {
        $logs = get_option($this->option_key, []);
        
        if (!is_array($logs)) {
            return false;
        }
        
        $original_count = count($logs);
        
        // Filter out the draft with matching ID
        $logs = array_filter($logs, function($log) use ($id) {
            return !(isset($log['id']) && $log['id'] === $id && isset($log['status']) && $log['status'] === 'draft');
        });
        
        // Re-index array
        $logs = array_values($logs);
        
        // Save back
        $result = update_option($this->option_key, $logs);
        
        // Return true if count changed and update succeeded
        return $result && count($logs) < $original_count;
    }
    
    /**
     * Convert draft to sent status
     *
     * @param string $id      Draft entry ID
     * @param int    $sent_at Timestamp when sent
     * @return bool True on success, false on failure
     */
    public function convert_draft_to_sent(string $id, int $sent_at): bool {
        $logs = get_option($this->option_key, []);
        
        if (!is_array($logs)) {
            return false;
        }
        
        $found = false;
        
        foreach ($logs as $index => $log) {
            if (isset($log['id']) && $log['id'] === $id && isset($log['status']) && $log['status'] === 'draft') {
                // Update status to sent
                $logs[$index]['status'] = 'sent';
                $logs[$index]['sent_at'] = $sent_at;
                $logs[$index]['updated_at'] = $sent_at;
                
                // Generate body_preview if not exists
                if (!isset($logs[$index]['body_preview']) && isset($logs[$index]['body'])) {
                    $body_preview = mb_substr(strip_tags($logs[$index]['body']), 0, 100);
                    if (mb_strlen(strip_tags($logs[$index]['body'])) > 100) {
                        $body_preview .= '...';
                    }
                    $logs[$index]['body_preview'] = $body_preview;
                }
                
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        return update_option($this->option_key, $logs);
    }
}

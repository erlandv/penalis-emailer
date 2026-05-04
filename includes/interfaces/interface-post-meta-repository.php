<?php
/**
 * Post Meta Repository Interface
 *
 * Defines the contract for post meta data access.
 * Implementations can use WordPress post meta or other storage mechanisms.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Post_Meta_Repository_Interface
 *
 * Contract for post meta storage operations.
 */
interface Penalis_Post_Meta_Repository_Interface {
    
    /**
     * Save meta value for a post
     *
     * @param int   $post_id Post ID
     * @param mixed $value   Value to store
     * @return bool True on success, false on failure
     */
    public function save(int $post_id, $value): bool;
    
    /**
     * Get meta value for a post
     *
     * @param int $post_id Post ID
     * @return mixed Meta value or empty string if not found
     */
    public function get(int $post_id);
    
    /**
     * Check if meta exists for a post
     *
     * @param int $post_id Post ID
     * @return bool True if meta exists, false otherwise
     */
    public function exists(int $post_id): bool;
    
    /**
     * Delete meta for a post
     *
     * @param int $post_id Post ID
     * @return bool True on success, false on failure
     */
    public function delete(int $post_id): bool;
    
    /**
     * Get all posts that have this meta key
     *
     * @param int $limit Optional limit (-1 = no limit)
     * @return array Array of post IDs
     */
    public function get_posts_with_meta(int $limit = -1): array;
}

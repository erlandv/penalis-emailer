<?php
/**
 * Post Meta Repository
 *
 * WordPress Post Meta API implementation for tracking automatic emails.
 * Stores email sent status in post meta.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Post_Meta_Repository
 *
 * Manages post meta data for email tracking.
 */
class Penalis_Post_Meta_Repository implements Penalis_Post_Meta_Repository_Interface {
    
    /**
     * Meta key for storing data
     *
     * @var string
     */
    private $meta_key;
    
    /**
     * Constructor
     *
     * @param string $meta_key Post meta key
     */
    public function __construct(string $meta_key) {
        $this->meta_key = $meta_key;
    }
    
    /**
     * Save meta value for a post
     *
     * @param int   $post_id Post ID
     * @param mixed $value   Value to store
     * @return bool True on success, false on failure
     */
    public function save(int $post_id, $value): bool {
        return update_post_meta($post_id, $this->meta_key, $value) !== false;
    }
    
    /**
     * Get meta value for a post
     *
     * @param int $post_id Post ID
     * @return mixed Meta value or empty string if not found
     */
    public function get(int $post_id) {
        return get_post_meta($post_id, $this->meta_key, true);
    }
    
    /**
     * Check if meta exists for a post
     *
     * @param int $post_id Post ID
     * @return bool True if meta exists, false otherwise
     */
    public function exists(int $post_id): bool {
        $value = $this->get($post_id);
        return !empty($value);
    }
    
    /**
     * Delete meta for a post
     *
     * @param int $post_id Post ID
     * @return bool True on success, false on failure
     */
    public function delete(int $post_id): bool {
        return delete_post_meta($post_id, $this->meta_key);
    }
    
    /**
     * Get all posts that have this meta key
     *
     * @param int $limit Optional limit
     * @return array Array of post IDs
     */
    public function get_posts_with_meta(int $limit = -1): array {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'meta_key' => $this->meta_key,
            'fields' => 'ids',
        ];
        
        $query = new WP_Query($args);
        return $query->posts;
    }
}

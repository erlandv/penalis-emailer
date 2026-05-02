<?php
/**
 * Markdown Parser Interface
 *
 * Contract for markdown parsing functionality.
 * Defines methods for converting markdown to HTML.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface Penalis_Markdown_Parser_Interface
 *
 * Defines the contract for markdown parsing services.
 */
interface Penalis_Markdown_Parser_Interface {
    
    /**
     * Parse markdown text to HTML
     *
     * @param string $text Markdown text
     * @return string HTML output
     */
    public function parse(string $text): string;
    
    /**
     * Parse inline markdown (bold, italic, links)
     *
     * @param string $text Text with inline markdown
     * @return string HTML output
     */
    public function parse_inline(string $text): string;
}

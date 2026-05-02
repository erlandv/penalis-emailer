<?php
/**
 * Markdown Parser Class
 *
 * Handles conversion of markdown-style text to HTML for email templates.
 * Supports basic markdown syntax: bold, italic, links, lists, buttons.
 *
 * @package Penalis_Emailer
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Markdown_Parser
 *
 * Converts markdown text to HTML with email-safe formatting.
 */
class Penalis_Markdown_Parser {
    
    /**
     * Parse markdown text to HTML
     *
     * @param string $text Plain text with markdown formatting
     * @return string HTML formatted content
     */
    public function parse(string $text): string {
        // Normalize line endings
        $text = $this->normalize_line_endings($text);
        
        // Split by double newline to get paragraphs
        $paragraphs = explode("\n\n", $text);
        $html = '';
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            
            if (empty($paragraph)) {
                continue;
            }
            
            $html .= $this->process_paragraph($paragraph);
        }
        
        return $html;
    }
    
    /**
     * Normalize line endings to \n
     *
     * @param string $text Text with mixed line endings
     * @return string Text with normalized line endings
     */
    private function normalize_line_endings(string $text): string {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
    
    /**
     * Process a single paragraph
     *
     * @param string $paragraph Paragraph text
     * @return string HTML formatted paragraph
     */
    private function process_paragraph(string $paragraph): string {
        // Split paragraph into lines
        $lines = explode("\n", $paragraph);
        $in_list = false;
        $in_ordered_list = false;
        $paragraph_content = '';
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (empty($trimmed)) {
                continue;
            }
            
            // Unordered list item (- item or * item)
            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
                // Close ordered list if open
                if ($in_ordered_list) {
                    $paragraph_content .= '</ol>';
                    $in_ordered_list = false;
                }
                
                // Open list if not already open
                if (!$in_list) {
                    $paragraph_content .= '<ul style="margin:0 0 12px 0; padding-left:20px;">';
                    $in_list = true;
                }
                
                $paragraph_content .= '<li>' . $this->format_inline_markdown($matches[1]) . '</li>';
                continue;
            }
            
            // Ordered list item (1. item)
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
                // Close unordered list if open
                if ($in_list) {
                    $paragraph_content .= '</ul>';
                    $in_list = false;
                }
                
                // Open list if not already open
                if (!$in_ordered_list) {
                    $paragraph_content .= '<ol style="margin:0 0 12px 0; padding-left:20px;">';
                    $in_ordered_list = true;
                }
                
                $paragraph_content .= '<li>' . $this->format_inline_markdown($matches[1]) . '</li>';
                continue;
            }
            
            // Regular text line
            // Close any open lists
            if ($in_list) {
                $paragraph_content .= '</ul>';
                $in_list = false;
            }
            if ($in_ordered_list) {
                $paragraph_content .= '</ol>';
                $in_ordered_list = false;
            }
            
            // Add line with <br> if not first line in paragraph
            if (!empty($paragraph_content) && !$in_list && !$in_ordered_list) {
                $paragraph_content .= '<br>';
            }
            
            $paragraph_content .= $this->format_inline_markdown($trimmed);
        }
        
        // Close any remaining open lists
        if ($in_list) {
            $paragraph_content .= '</ul>';
        }
        if ($in_ordered_list) {
            $paragraph_content .= '</ol>';
        }
        
        // Wrap in paragraph if not a list
        if (!empty($paragraph_content)) {
            // Check if content is only lists (starts with <ul> or <ol>)
            if (preg_match('/^<(ul|ol)/', $paragraph_content)) {
                return $paragraph_content;
            } else {
                return '<p style="margin:0 0 12px 0;">' . $paragraph_content . '</p>';
            }
        }
        
        return '';
    }
    
    /**
     * Format inline markdown (bold, italic, links, buttons)
     *
     * @param string $text Text with inline markdown
     * @return string HTML formatted text
     */
    private function format_inline_markdown(string $text): string {
        $link_placeholders = [];
        $autolink_placeholders = [];
        $button_placeholders = [];
        
        // Process button markdown FIRST: [button: text](url)
        $text = preg_replace_callback(
            '/\[button:\s*([^\]]+)\]\(([^\)]+)\)/',
            function($matches) use (&$button_placeholders) {
                $button_text = trim($matches[1]);
                $url = $matches[2];
                $placeholder = '{{BUTTON' . count($button_placeholders) . '}}';
                
                // Generate button HTML with same styling as default template
                $button_html = $this->generate_button_html($button_text, $url);
                
                $button_placeholders[$placeholder] = $button_html;
                return $placeholder;
            },
            $text
        );
        
        // Process markdown links: [text](url)
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^\)]+)\)/',
            function($matches) use (&$link_placeholders) {
                $link_text = $matches[1];
                $url = $matches[2];
                $placeholder = '{{MDLINK' . count($link_placeholders) . '}}';
                $link_placeholders[$placeholder] = '<a href="' . esc_url($url) . '" style="color:#3D55EF; text-decoration:underline;" target="_blank">' . esc_html($link_text) . '</a>';
                return $placeholder;
            },
            $text
        );
        
        // Auto-link URLs (http:// or https://)
        $text = preg_replace_callback(
            '/(https?:\/\/[^\s<\[{]+)/',
            function($matches) use (&$autolink_placeholders) {
                $url = $matches[1];
                $placeholder = '{{AUTOLINK' . count($autolink_placeholders) . '}}';
                $autolink_placeholders[$placeholder] = '<a href="' . esc_url($url) . '" style="color:#3D55EF; text-decoration:underline;" target="_blank">' . esc_html($url) . '</a>';
                return $placeholder;
            },
            $text
        );
        
        // NOW escape HTML for the rest of the text
        $text = esc_html($text);
        
        // Bold: **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Italic: *text* or _text_ (but not in URLs or already processed bold)
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/', '<em>$1</em>', $text);
        
        // Replace button placeholders with actual HTML (FIRST, before links)
        foreach ($button_placeholders as $placeholder => $html) {
            $text = str_replace($placeholder, $html, $text);
        }
        
        // Replace link placeholders with actual HTML
        foreach ($link_placeholders as $placeholder => $html) {
            $text = str_replace($placeholder, $html, $text);
        }
        
        // Replace autolink placeholders with actual HTML
        foreach ($autolink_placeholders as $placeholder => $html) {
            $text = str_replace($placeholder, $html, $text);
        }
        
        return $text;
    }
    
    /**
     * Generate button HTML
     *
     * @param string $text  Button text
     * @param string $url   Button URL
     * @return string Button HTML
     */
    private function generate_button_html(string $text, string $url): string {
        $button_html = '<table role="presentation" border="0" cellspacing="0" cellpadding="0" style="margin: 16px 0;">';
        $button_html .= '<tr>';
        $button_html .= '<td align="center" bgcolor="#3D55EF" style="border-radius: 4px;">';
        $button_html .= '<a href="' . esc_url($url) . '" target="_blank" style="font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-weight: 600; color: #ffffff; text-decoration: none; padding: 8px 16px; border-radius: 4px; display: inline-block;">';
        $button_html .= esc_html($text);
        $button_html .= '</a>';
        $button_html .= '</td>';
        $button_html .= '</tr>';
        $button_html .= '</table>';
        
        return $button_html;
    }
    
    /**
     * Parse inline markdown only (without paragraph wrapping)
     * Useful for single-line text like subjects or short descriptions
     *
     * @param string $text Text with inline markdown
     * @return string HTML formatted text
     */
    public function parse_inline(string $text): string {
        return $this->format_inline_markdown($text);
    }
}

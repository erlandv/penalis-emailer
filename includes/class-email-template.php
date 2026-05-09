<?php
/**
 * Email Template Class
 * 
 * Manages HTML email template and placeholder replacement.
 * Supports flexible body content with markdown formatting.
 * 
 * @package Penalis_Emailer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Email_Template
 * 
 * Handles email template rendering with placeholder replacement and markdown support.
 */
class Penalis_Email_Template implements Penalis_Email_Template_Interface {
    
    /**
     * Markdown parser instance
     * 
     * @var Penalis_Markdown_Parser
     */
    private $markdown_parser;
    
    /**
     * Constructor
     * 
     * @param Penalis_Markdown_Parser $markdown_parser Markdown parser instance
     */
    public function __construct(Penalis_Markdown_Parser $markdown_parser) {
        $this->markdown_parser = $markdown_parser;
    }
    
    /**
     * Render flexible email with custom body content
     * Used for manual emails with markdown support
     * 
     * @param string $body_content   Plain text/markdown body content
     * @param array  $user_data      User data for personalization (display_name, user_email, user_login)
     * @param string $preheader_text Optional preheader text for email preview
     * @return string Complete HTML email
     */
    public function render_flexible_email(string $body_content, array $user_data = [], string $preheader_text = ''): string {
        // Replace user placeholders first
        $body_content = $this->replace_user_placeholders($body_content, $user_data);
        
        // Convert markdown to HTML using parser
        $body_html = $this->markdown_parser->parse($body_content);
        
        // Wrap in body container
        $body_section = $this->get_body_wrapper_html($body_html);
        
        // Combine header + body + footer
        return $this->get_header_html($preheader_text) . $body_section . $this->get_footer_html();
    }
    
    /**
     * Render auto-email with editable template
     * Used for automatic post publish emails
     * 
     * @param array $placeholders Associative array with post data (post_title, post_url, author_name)
     * @return string Complete HTML email
     */
    public function render_auto_email(array $placeholders): string {
        // Get custom template body or use default
        $template_body = get_option('penalis_auto_email_body', '');
        
        if (empty($template_body)) {
            $template_body = $this->get_default_auto_email_body();
        }
        
        // Prepare user data for placeholder replacement
        $user_data = [
            'display_name' => $placeholders['AUTHOR_NAME'] ?? '',
            'author_name' => $placeholders['AUTHOR_NAME'] ?? '',
            'post_title' => $placeholders['POST_TITLE'] ?? '',
            'post_url' => $placeholders['POST_URL'] ?? ''
        ];
        
        // Replace placeholders
        $body_content = $this->replace_user_placeholders($template_body, $user_data);
        
        // Convert markdown to HTML using parser
        $body_html = $this->markdown_parser->parse($body_content);
        
        // Wrap in body container
        $body_section = $this->get_body_wrapper_html($body_html);
        
        // Preheader for automatic emails
        $preheader = 'Tulisanmu sudah terbit di Penalis. Yuk cek sekarang!';
        
        // Combine header + body + footer
        return $this->get_header_html($preheader) . $body_section . $this->get_footer_html();
    }
    
    /**
     * Generate HTML email from manual content (interface implementation)
     *
     * @param string $subject   Email subject
     * @param string $body      Email body content
     * @param string $from_name Sender name
     * @return string Complete HTML email
     */
    public function generate_manual_email(string $subject, string $body, string $from_name): string {
        // Use render_flexible_email for implementation
        return $this->render_flexible_email($body, ['display_name' => $from_name]);
    }
    
    /**
     * Generate HTML email from auto template (interface implementation)
     *
     * @param int $post_id Post ID
     * @return string Complete HTML email
     */
    public function generate_auto_email(int $post_id): string {
        // Get post data
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        
        $author = get_userdata($post->post_author);
        if (!$author) {
            return '';
        }
        
        // Prepare placeholders
        $placeholders = [
            'AUTHOR_NAME' => $author->display_name,
            'POST_TITLE' => $post->post_title,
            'POST_URL' => get_permalink($post_id)
        ];
        
        // Use render_auto_email for implementation
        return $this->render_auto_email($placeholders);
    }
    
    /**
     * Get email wrapper HTML (interface implementation)
     *
     * @param string $content        Email content
     * @param string $preheader_text Optional preheader text
     * @return string Complete HTML with wrapper
     */
    public function get_email_wrapper(string $content, string $preheader_text = ''): string {
        return $this->get_header_html($preheader_text) . $this->get_body_wrapper_html($content) . $this->get_footer_html();
    }
    
    /**
     * Get default auto-email body template (plain text/markdown)
     * 
     * @return string Default template body
     */
    public function get_default_auto_email_body(): string {
        return "Halo {AUTHOR_NAME},

Selamat! Karya tulismu yang berjudul **{POST_TITLE}** sudah terbit di Penalis.
Yuk langsung baca dan bagikan tulisanmu:

{BUTTON_CTA}

Kami percaya setiap tulisan punya kekuatan untuk membawa dampak.
Mari terus berbagi dan menjadi bermanfaat bersama para kontributor Penalis.

Kami selalu menunggu tulisan terbarumu yang dapat dikirim melalui [Submit Karya Tulis](https://penalis.com/submit-karya-tulis/).

Untuk mendapatkan info terbaru, bergabunglah bersama kami di [WhatsApp Group](https://penalis.com/whatsapp-group/).

Salam Literasi,
**Penalis**";
    }
    
    /**
     * Get email header HTML
     * 
     * @param string $preheader_text Optional preheader text for email preview
     * @return string HTML header section
     */
    public function get_header_html(string $preheader_text = ''): string {
        ob_start();
        ?>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="margin:0; padding:0;">
<tr>
<td align="center" style="padding: 20px 10px;">

<?php if (!empty($preheader_text)): ?>
<!-- Preheader (hidden preview text) -->
<span style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; color:#ffffff; line-height:1;"><?php echo esc_html($preheader_text); ?> &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</span>
<?php endif; ?>

<!-- Main Container -->
<table role="presentation" border="0" width="600" cellspacing="0" cellpadding="0" style="width:100%; max-width:600px; background-color:#FEFEFE; border-radius:8px; overflow:hidden; border:1px solid #e5e5e5;">
<tr>
<td>

<!-- Header -->
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td style="background-color:#f0f0f0; padding:16px;">
<img src="<?php echo esc_url(Penalis_Config::get_logo_url()); ?>" alt="Logo Penalis" width="130" style="display:block; border:0; height:auto;">
</td>
</tr>
</table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get email footer HTML
     * 
     * @return string HTML footer section
     */
    public function get_footer_html(): string {
        ob_start();
        ?>
<!-- Footer -->
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td style="background-color:#f0f0f0; padding:20px; font-size:12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#777777;">
<p style="margin:0;"><i>Kamu menerima email ini karena terdaftar sebagai kontributor Penalis.<br>Jika ada pertanyaan atau kendala, kamu bisa menghubungi kami di admin@penalis.com</i></p>
</td>
</tr>
</table>

</td>
</tr>
</table>

</td>
</tr>
</table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get body wrapper HTML with custom content
     * 
     * @param string $body_html HTML content for body
     * @return string Wrapped body HTML
     */
    public function get_body_wrapper_html(string $body_html): string {
        ob_start();
        ?>
<!-- Body -->
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td style="padding:20px; font-size:14px; color:#333333; line-height:1.6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
<?php echo $body_html; ?>
</td>
</tr>
</table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Replace user-specific placeholders in content
     * 
     * @param string $content   Content with placeholders
     * @param array  $user_data User data array (display_name, user_email, user_login)
     * @return string Content with replaced placeholders
     */
    private function replace_user_placeholders(string $content, array $user_data): string {
        $placeholders = [
            // New English placeholder names
            '{USER_NAME}' => isset($user_data['display_name']) ? $user_data['display_name'] : '',
            '{USER_EMAIL}' => isset($user_data['user_email']) ? $user_data['user_email'] : '',
            '{USERNAME}' => isset($user_data['user_login']) ? $user_data['user_login'] : '',
            '{DATE}' => date_i18n(get_option('date_format')),
            '{SITE_NAME}' => get_bloginfo('name'),
            '{SITE_URL}' => home_url(),
            
            // Backward compatibility: Old Indonesian placeholder names (deprecated)
            '{NAMA_USER}' => isset($user_data['display_name']) ? $user_data['display_name'] : '',
            '{EMAIL_USER}' => isset($user_data['user_email']) ? $user_data['user_email'] : '',
            '{TANGGAL}' => date_i18n(get_option('date_format'))
        ];
        
        // Add post-specific placeholders if available
        if (isset($user_data['post_title'])) {
            $placeholders['{POST_TITLE}'] = $user_data['post_title'];
        }
        if (isset($user_data['post_url'])) {
            $placeholders['{POST_URL}'] = $user_data['post_url'];
        }
        if (isset($user_data['author_name'])) {
            $placeholders['{AUTHOR_NAME}'] = $user_data['author_name'];
        }
        
        // Replace {BUTTON_CTA} with button markdown syntax
        if (isset($user_data['post_url'])) {
            $placeholders['{BUTTON_CTA}'] = '[button: Baca Tulisanmu](' . $user_data['post_url'] . ')';
        }
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }
    
}


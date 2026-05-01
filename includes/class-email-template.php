<?php
/**
 * Email Template Class
 * 
 * Manages HTML email template and placeholder replacement.
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
 * Handles email template rendering with placeholder replacement.
 */
class Penalis_Email_Template {
    
    /**
     * Logo URL for email header
     * 
     * @var string
     */
    private $logo_url = 'https://penalis.com/wp-content/uploads/2021/01/logo-penalis.png';
    
    /**
     * Database option key for storing custom template
     * 
     * @var string
     */
    private $template_option_key = 'penalis_email_template';
    
    /**
     * Render email template with placeholder replacement
     * 
     * @param array  $placeholders   Associative array of placeholder values
     * @param bool   $use_template   Whether to use template or custom message
     * @param string $custom_message Custom message content (used when use_template is false)
     * @return string Rendered HTML email content
     */
    public function render(array $placeholders, bool $use_template = true, string $custom_message = ''): string {
        if (!$use_template) {
            return $custom_message;
        }
        
        $html = $this->get_template_html();
        return $this->replace_placeholders($html, $placeholders);
    }
    
    /**
     * Get email template HTML (from database or default)
     * 
     * @return string HTML email template
     */
    public function get_template_html(): string {
        // Try to load from database first
        $custom_template = get_option($this->template_option_key, '');
        
        if (!empty($custom_template)) {
            return $custom_template;
        }
        
        // Fallback to default template
        return $this->get_default_template_html();
    }
    
    /**
     * Get default HTML email template using output buffering
     * 
     * @return string HTML email template
     */
    public function get_default_template_html(): string {
        ob_start();
        ?>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="margin:0; padding:0;">
<tr>
<td align="center" style="padding: 20px 10px;">

<!-- Preheader (hidden preview text) -->
<span style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; color:#ffffff; line-height:1;">Tulisanmu sudah terbit di Penalis. Yuk cek sekarang! &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</span>

<!-- Main Container -->
<table role="presentation" border="0" width="600" cellspacing="0" cellpadding="0" style="width:100%; max-width:600px; background-color:#FEFEFE; border-radius:8px; overflow:hidden; border:1px solid #e5e5e5;">
<tr>
<td>

<!-- Header -->
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td style="background-color:#f0f0f0; padding:16px;">
<img src="<?php echo esc_url($this->logo_url); ?>" alt="Logo Penalis" width="130" style="display:block; border:0; height:auto;">
</td>
</tr>
</table>

<!-- Body -->
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tr>
<td style="padding:20px; font-size:14px; color:#333333; line-height:1.6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

<p style="margin:0 0 12px 0;">Halo AUTHOR_NAME,</p>

<p style="margin:0 0 12px 0;">Selamat! Karya tulismu yang berjudul <b>POST_TITLE</b> sudah terbit di Penalis.<br>Yuk langsung baca dan bagikan tulisanmu:</p>

<!-- CTA Button -->
<table role="presentation" border="0" cellspacing="0" cellpadding="0" style="margin: 16px 0;">
<tr>
<td align="center" bgcolor="#3D55EF" style="border-radius: 4px;">
<a href="POST_URL" target="_blank" style="font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-weight: 600; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 4px; display: inline-block; mso-padding-alt: 0;">
<!--[if mso]>
<i style="mso-font-width: -100%; mso-text-raise: 30pt;">&nbsp;</i>
<![endif]-->
<span style="mso-text-raise: 15pt;">Baca Tulisanmu</span>
<!--[if mso]>
<i style="mso-font-width: -100%;">&nbsp;</i>
<![endif]-->
</a>
</td>
</tr>
</table>

<p style="margin:0 0 12px 0;">Kami percaya setiap tulisan punya kekuatan untuk membawa dampak.<br>Mari terus berbagi dan menjadi bermanfaat bersama para kontributor Penalis.</p>

<p style="margin:0 0 12px 0;">Kami selalu menunggu tulisan terbarumu yang dapat dikirim melalui <a href="https://penalis.com/submit-karya-tulis/" target="_blank" style="color:#3D55EF; text-decoration:underline;">Submit Karya Tulis</a>.</p>

<p style="margin:0 0 12px 0;">Untuk mendapatkan info terbaru, bergabunglah bersama kami di <a href="https://penalis.com/whatsapp-group/" target="_blank" style="color:#3D55EF; text-decoration:underline;">WhatsApp Group</a>.</p>

<p style="margin:0;">Salam Literasi,<br><b>Penalis</b></p>

</td>
</tr>
</table>

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
     * Replace placeholders in HTML with actual values
     * 
     * @param string $html         HTML template with placeholders
     * @param array  $placeholders Associative array of placeholder values
     * @return string HTML with replaced placeholders
     */
    private function replace_placeholders(string $html, array $placeholders): string {
        $search = [
            'AUTHOR_NAME',
            'POST_TITLE',
            'POST_URL'
        ];
        
        // Get values and ensure they're clean (already cleaned in sender class)
        $author_name = isset($placeholders['AUTHOR_NAME']) ? $placeholders['AUTHOR_NAME'] : '';
        $post_title = isset($placeholders['POST_TITLE']) ? $placeholders['POST_TITLE'] : '';
        $post_url = isset($placeholders['POST_URL']) ? $placeholders['POST_URL'] : '';
        
        $replace = [
            esc_html($author_name),
            esc_html($post_title),
            esc_url($post_url)
        ];
        
        return str_replace($search, $replace, $html);
    }
}

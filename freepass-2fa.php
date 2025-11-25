<?php
/**
 * Plugin Name: Freepass 2FA
 * Plugin URI: https://trueresort.com
 * Description: Simple two-factor authentication plugin for WordPress login.
 * Version: 1.0.0
 * Author: Mike Art (@trueresort)
 * Author URI: https://trueresort.com
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FREEPASS_VERSION', '1.0.0');
define('FREEPASS_URL', plugin_dir_url(__FILE__));
define('FREEPASS_PATH', plugin_dir_path(__FILE__));

class Freepass_2FA {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_login', array($this, 'handle_login'), 10, 2);
        add_action('init', array($this, 'handle_2fa_verify'));
        add_action('login_enqueue_scripts', array($this, 'login_assets'));
        add_action('wp_ajax_freepass_generate_secret', array($this, 'ajax_generate_secret'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        if (!get_option('freepass_version')) {
            add_option('freepass_enabled', 0);
            add_option('freepass_secret', '');
            add_option('freepass_version', FREEPASS_VERSION);
        }
    }
    
    public function admin_menu() {
        add_options_page(
            'Freepass 2FA',
            'Freepass 2FA',
            'manage_options',
            'freepass-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_assets($hook) {
        if ($hook !== 'settings_page_freepass-settings') {
            return;
        }
        
        wp_enqueue_style('freepass-admin', FREEPASS_URL . 'admin.css', array(), FREEPASS_VERSION);
        wp_enqueue_script('freepass-admin', FREEPASS_URL . 'admin.js', array('jquery'), FREEPASS_VERSION, true);
        
        wp_localize_script('freepass-admin', 'freepass_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('freepass_nonce')
        ));
    }
    
    public function login_assets() {
        wp_enqueue_style('freepass-login', FREEPASS_URL . '2fa-form.css', array(), FREEPASS_VERSION);
    }
    
    public function settings_page() {
        if ($_POST && check_admin_referer('freepass_settings')) {
            $enabled = isset($_POST['enabled']) ? 1 : 0;
            update_option('freepass_enabled', $enabled);
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $enabled = get_option('freepass_enabled', 0);
        $secret = get_option('freepass_secret', '');
        ?>
        <div class="wrap">
            <h1>Freepass 2FA Settings</h1>
            
            <form method="post">
                <?php wp_nonce_field('freepass_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th>Enable 2FA</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                                Enable two-factor authentication for all users
                            </label>
                        </td>
                    </tr>
                    
                    <?php if ($enabled): ?>
                    <tr>
                        <th>QR Code Setup</th>
                        <td>
                            <?php if ($secret): ?>
                                <div id="qr-display">
                                    <img src="<?php echo esc_attr($this->get_qr_url($secret)); ?>" alt="QR Code">
                                    <p>Scan this QR code with Google Authenticator app</p>
                                    <p><strong>Secret Key:</strong> <code><?php echo esc_html($secret); ?></code></p>
                                </div>
                            <?php else: ?>
                                <button type="button" id="generate-secret" class="button button-primary">Generate QR Code</button>
                                <div id="qr-display" style="display: none;"></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <?php if ($enabled): ?>
            <div class="freepass-info">
                <h2>Instructions</h2>
                <ol>
                    <li>Install Google Authenticator app on your phone</li>
                    <li>Scan the QR code above or enter the secret key manually</li>
                    <li>Use the 6-digit code from the app when logging in</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function handle_login($user_login, $user) {
        if (!get_option('freepass_enabled')) {
            return;
        }
        
        $secret = get_option('freepass_secret');
        if (empty($secret)) {
            return;
        }
        
        wp_clear_auth_cookie();
        set_transient('freepass_pending_user', $user->ID, 600);
        
        wp_redirect(wp_login_url() . '?freepass_2fa=verify');
        exit;
    }
    
    public function handle_2fa_verify() {
        if (!isset($_GET['freepass_2fa']) || $_GET['freepass_2fa'] !== 'verify') {
            return;
        }
        
        $user_id = get_transient('freepass_pending_user');
        if (!$user_id) {
            wp_redirect(wp_login_url());
            exit;
        }
        
        $error = '';
        
        if ($_POST && isset($_POST['freepass_code'])) {
            $code = sanitize_text_field($_POST['freepass_code']);
            $secret = get_option('freepass_secret');
            
            if ($this->verify_totp($secret, $code)) {
                delete_transient('freepass_pending_user');
                wp_set_auth_cookie($user_id, true);
                wp_redirect(admin_url());
                exit;
            } else {
                $error = 'Invalid authentication code';
            }
        }
        
        $this->show_2fa_form($error);
    }
    
    private function show_2fa_form($error = '') {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php bloginfo('name'); ?> - Two-Factor Authentication</title>
            <link rel="stylesheet" href="<?php echo FREEPASS_URL; ?>2fa-form.css?v=<?php echo FREEPASS_VERSION; ?>">
        </head>
        <body class="freepass-2fa-page">
            <div id="freepass-container">
                <h1 class="freepass-title"><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></h1>
                
                <form method="post" class="freepass-form">
                    <p class="freepass-field">
                        <label for="freepass_code" class="freepass-label">Authentication Code</label>
                        <input type="text" name="freepass_code" id="freepass_code" class="freepass-input" maxlength="6" 
                               placeholder="000000" autofocus required autocomplete="off">
                    </p>
                    
                    <?php if ($error): ?>
                    <div class="freepass-error"><?php echo esc_html($error); ?></div>
                    <?php endif; ?>
                    
                    <p class="freepass-submit">
                        <input type="submit" value="Verify Code" class="freepass-button">
                    </p>
                </form>
                
                <p class="freepass-nav">
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="freepass-link">← Back to Login</a>
                </p>
            </div>
            
            <script>
                document.getElementById('freepass_code').addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                    if (e.target.value.length === 6) {
                        setTimeout(() => e.target.form.submit(), 200);
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    public function ajax_generate_secret() {
        if (!wp_verify_nonce($_POST['nonce'], 'freepass_nonce') || !current_user_can('manage_options')) {
            wp_die();
        }
        
        $secret = $this->generate_secret();
        update_option('freepass_secret', $secret);
        
        $qr_url = $this->get_qr_url($secret);
        
        wp_send_json_success(array(
            'secret' => $secret,
            'qr' => $qr_url
        ));
    }
    
    private function generate_secret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    private function get_qr_url($secret) {
        require_once FREEPASS_PATH . 'qrcode.php';
        
        $issuer = get_bloginfo('name');
        $account = get_bloginfo('admin_email');
        $qr_data = 'otpauth://totp/' . urlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . urlencode($issuer);
        
        ob_start();
        $qr = new QRCode($qr_data);
        $qr->output_image();
        $image_data = ob_get_contents();
        ob_end_clean();
        
        return 'data:image/png;base64,' . base64_encode($image_data);
    }
    
    private function verify_totp($secret, $code) {
        $time = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $calculated = $this->calculate_totp($secret, $time + $i);
            if ($calculated === str_pad($code, 6, '0', STR_PAD_LEFT)) {
                return true;
            }
        }
        return false;
    }
    
    private function calculate_totp($secret, $time) {
        $secret = $this->base32_decode($secret);
        $time = pack('N*', 0, $time);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord($hash[19]) & 0xf;
        
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    private function base32_decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $value = strpos($alphabet, $input[$i]);
            if ($value === false) continue;
            
            $v = ($v << 5) | $value;
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr(($v >> ($vbits - 8)) & 255);
                $vbits -= 8;
            }
        }
        
        return $output;
    }
    
    public function activate() {
        add_option('freepass_enabled', 0);
        add_option('freepass_secret', '');
        add_option('freepass_version', FREEPASS_VERSION);
    }
}

new Freepass_2FA();
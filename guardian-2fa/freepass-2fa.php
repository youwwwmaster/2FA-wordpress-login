<?php
/**
 * Plugin Name: Guardian 2FA
 * Plugin URI: https://trueresort.com
 * Description: Per-user two-factor authentication (TOTP) for WordPress login.
 * Version: 0.2.0
 * Author: Mike Art (@trueresort)
 * Author URI: https://trueresort.com
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FREEPASS_VERSION', '0.2.0');
define('FREEPASS_URL', plugin_dir_url(__FILE__));
define('FREEPASS_PATH', plugin_dir_path(__FILE__));

class Freepass_2FA {

    const META_SECRET    = 'freepass_2fa_secret';
    const META_PENDING   = 'freepass_2fa_pending_secret';
    const META_LAST_STEP = 'freepass_2fa_last_step';
    const COOKIE         = 'freepass_2fa_token';
    const LOGIN_TTL      = 600;
    const MAX_ATTEMPTS   = 5;

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

        // Login flow
        add_action('wp_login', array($this, 'handle_login'), 10, 2);
        add_action('login_init', array($this, 'handle_2fa_verify'));
        add_filter('authenticate', array($this, 'block_xmlrpc_for_2fa_users'), 100, 1);

        // Per-user setup in the profile
        add_action('show_user_profile', array($this, 'profile_section'));
        add_action('edit_user_profile', array($this, 'profile_section'));
        add_action('personal_options_update', array($this, 'profile_save'));
        add_action('edit_user_profile_update', array($this, 'profile_save'));

        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    /* ---------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------- */

    public function admin_menu() {
        add_options_page('Guardian 2FA', 'Guardian 2FA', 'manage_options', 'freepass-settings', array($this, 'settings_page'));
    }

    public function admin_assets($hook) {
        if (in_array($hook, array('settings_page_freepass-settings', 'profile.php', 'user-edit.php'), true)) {
            wp_enqueue_style('freepass-admin', FREEPASS_URL . 'admin.css', array(), FREEPASS_VERSION);
        }
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have access to this page.'));
        }

        if (isset($_POST['freepass_settings_submit']) && check_admin_referer('freepass_settings')) {
            update_option('freepass_enabled', isset($_POST['enabled']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $enabled = (int) get_option('freepass_enabled', 0);
        ?>
        <div class="wrap">
            <h1>Guardian 2FA Settings</h1>
            <form method="post">
                <?php wp_nonce_field('freepass_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Enable 2FA</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($enabled, 1); ?>>
                                Require a 2FA code at login for every user who has set up 2FA
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Changes', 'primary', 'freepass_settings_submit'); ?>
            </form>
            <div class="freepass-info">
                <h2>How it works</h2>
                <ol>
                    <li>Each user sets up 2FA in <strong>Users → Profile → Guardian 2FA</strong> with their own authenticator app.</li>
                    <li>Users who have not set up 2FA log in with their password only.</li>
                    <li>Administrators can turn off 2FA for a user on that user's profile page.</li>
                </ol>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Profile: per-user setup
     * ------------------------------------------------------------------- */

    public function profile_section($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        $is_self = get_current_user_id() === (int) $user->ID;
        $secret  = get_user_meta($user->ID, self::META_SECRET, true);
        wp_nonce_field('freepass_profile_' . $user->ID, 'freepass_profile_nonce');
        ?>
        <h2 id="guardian-2fa">Guardian 2FA</h2>
        <table class="form-table" role="presentation">
        <?php if ($secret): ?>
            <tr>
                <th>Status</th>
                <td>
                    <p><strong>2FA is active</strong> for this account.</p>
                    <label><input type="checkbox" name="freepass_disable" value="1"> Turn off 2FA for this account</label>
                </td>
            </tr>
        <?php elseif ($is_self): ?>
            <?php
            $pending = get_user_meta($user->ID, self::META_PENDING, true);
            if (!$pending) {
                $pending = $this->generate_secret();
                update_user_meta($user->ID, self::META_PENDING, $pending);
            }
            ?>
            <tr>
                <th>Set up 2FA</th>
                <td>
                    <div id="qr-display">
                        <img src="<?php echo esc_attr($this->get_qr_url($pending, $user)); ?>" alt="QR code">
                        <p>Scan this QR code with an authenticator app, or enter the key manually:</p>
                        <p><code><?php echo esc_html($pending); ?></code></p>
                    </div>
                    <label for="freepass_confirm_code">Code from the app</label><br>
                    <input type="text" name="freepass_confirm_code" id="freepass_confirm_code" inputmode="numeric" maxlength="6" autocomplete="off">
                    <p class="description">Enter the 6-digit code and click <strong>Update Profile</strong> to turn on 2FA.</p>
                </td>
            </tr>
        <?php else: ?>
            <tr>
                <th>Status</th>
                <td><p>2FA is not set up. The user sets it up on their own profile page.</p></td>
            </tr>
        <?php endif; ?>
        </table>
        <?php
    }

    public function profile_save($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['freepass_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['freepass_profile_nonce'])), 'freepass_profile_' . $user_id)) {
            return;
        }

        if (!empty($_POST['freepass_disable'])) {
            delete_user_meta($user_id, self::META_SECRET);
            delete_user_meta($user_id, self::META_PENDING);
            delete_user_meta($user_id, self::META_LAST_STEP);
            return;
        }

        $code = isset($_POST['freepass_confirm_code']) ? preg_replace('/\D/', '', wp_unslash($_POST['freepass_confirm_code'])) : '';
        if ($code === '' || get_current_user_id() !== (int) $user_id) {
            return;
        }

        $pending = get_user_meta($user_id, self::META_PENDING, true);
        $step    = $pending ? $this->verify_totp($pending, $code, 0) : false;
        if ($step === false) {
            add_action('user_profile_update_errors', function ($errors) {
                $errors->add('freepass_code', '<strong>Guardian 2FA:</strong> the code is invalid. 2FA was not turned on.');
            });
            return;
        }

        update_user_meta($user_id, self::META_SECRET, $pending);
        update_user_meta($user_id, self::META_LAST_STEP, $step);
        delete_user_meta($user_id, self::META_PENDING);
    }

    /* ---------------------------------------------------------------------
     * Login flow
     * ------------------------------------------------------------------- */

    private function user_requires_2fa($user_id) {
        return (int) get_option('freepass_enabled', 0) === 1
            && (string) get_user_meta($user_id, self::META_SECRET, true) !== '';
    }

    public function handle_login($user_login, $user) {
        if (!$this->user_requires_2fa($user->ID)) {
            return;
        }

        wp_clear_auth_cookie();

        $token = bin2hex(random_bytes(32));
        set_transient($this->transient_key($token), array('user_id' => (int) $user->ID, 'attempts' => 0), self::LOGIN_TTL);
        $this->set_token_cookie($token, time() + self::LOGIN_TTL);

        wp_safe_redirect(add_query_arg('freepass_2fa', 'verify', wp_login_url()));
        exit;
    }

    public function handle_2fa_verify() {
        if (!isset($_GET['freepass_2fa']) || $_GET['freepass_2fa'] !== 'verify') {
            return;
        }

        $token   = isset($_COOKIE[self::COOKIE]) ? preg_replace('/[^a-f0-9]/', '', (string) $_COOKIE[self::COOKIE]) : '';
        $pending = (strlen($token) === 64) ? get_transient($this->transient_key($token)) : false;
        if (!is_array($pending) || empty($pending['user_id'])) {
            $this->end_pending_login($token);
            wp_safe_redirect(wp_login_url());
            exit;
        }

        $user_id = (int) $pending['user_id'];
        $error   = '';

        if (isset($_POST['freepass_code'])) {
            $code   = preg_replace('/\D/', '', wp_unslash($_POST['freepass_code']));
            $secret = get_user_meta($user_id, self::META_SECRET, true);
            $last   = (int) get_user_meta($user_id, self::META_LAST_STEP, true);
            $step   = $secret ? $this->verify_totp($secret, $code, $last) : false;

            if ($step !== false) {
                update_user_meta($user_id, self::META_LAST_STEP, $step);
                $this->end_pending_login($token);
                wp_set_auth_cookie($user_id, false);
                wp_safe_redirect(admin_url());
                exit;
            }

            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $this->end_pending_login($token);
                wp_safe_redirect(add_query_arg('freepass_2fa', 'locked', wp_login_url()));
                exit;
            }
            set_transient($this->transient_key($token), $pending, self::LOGIN_TTL);
            $error = 'Invalid authentication code. Attempts left: ' . (self::MAX_ATTEMPTS - $pending['attempts']) . '.';
        }

        $this->show_2fa_form($error);
    }

    /**
     * XML-RPC login does not pass through the 2FA form, so it is refused
     * for users who have 2FA set up.
     */
    public function block_xmlrpc_for_2fa_users($user) {
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST && $user instanceof WP_User && $this->user_requires_2fa($user->ID)) {
            return new WP_Error('freepass_2fa_xmlrpc', 'XML-RPC login is disabled for accounts with 2FA.');
        }
        return $user;
    }

    private function transient_key($token) {
        return 'freepass_2fa_' . hash('sha256', $token);
    }

    private function set_token_cookie($value, $expires) {
        setcookie(self::COOKIE, $value, array(
            'expires'  => $expires,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    private function end_pending_login($token) {
        if ($token !== '') {
            delete_transient($this->transient_key($token));
        }
        $this->set_token_cookie('', time() - 3600);
    }

    private function show_2fa_form($error = '') {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php bloginfo('name'); ?> - Two-Factor Authentication</title>
            <link rel="stylesheet" href="<?php echo esc_url(FREEPASS_URL . '2fa-form.css?v=' . FREEPASS_VERSION); ?>">
        </head>
        <body class="freepass-2fa-page">
            <div id="freepass-container">
                <h1 class="freepass-title"><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></h1>

                <form method="post" class="freepass-form">
                    <p class="freepass-field">
                        <label for="freepass_code" class="freepass-label">Authentication Code</label>
                        <input type="text" name="freepass_code" id="freepass_code" class="freepass-input" maxlength="6"
                               inputmode="numeric" placeholder="000000" autofocus required autocomplete="one-time-code">
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

    /* ---------------------------------------------------------------------
     * TOTP (RFC 6238, SHA-1, 30 s, 6 digits)
     * ------------------------------------------------------------------- */

    private function generate_secret() {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    private function get_qr_url($secret, $user) {
        require_once FREEPASS_PATH . 'qrcode.php';

        $issuer  = get_bloginfo('name');
        $account = $user->user_email ? $user->user_email : $user->user_login;
        $qr_data = 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);

        $qr    = new QRCode($qr_data);
        $image = $qr->render_image();
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Returns the matched time step, or false. Steps at or below $last_step
     * are rejected, so a code cannot be used twice.
     */
    private function verify_totp($secret, $code, $last_step) {
        if (!preg_match('/^\d{6}$/', (string) $code)) {
            return false;
        }
        $now = (int) floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $step = $now + $i;
            if ($step <= $last_step) {
                continue;
            }
            if (hash_equals($this->calculate_totp($secret, $step), $code)) {
                return $step;
            }
        }
        return false;
    }

    private function calculate_totp($secret, $step) {
        $key    = $this->base32_decode($secret);
        $time   = pack('N*', 0, $step);
        $hash   = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;

        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32_decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output   = '';
        $v        = 0;
        $vbits    = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $value = strpos($alphabet, $input[$i]);
            if ($value === false) {
                continue;
            }
            $v = (($v << 5) | $value) & 0xFFFFFF;
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
        add_option('freepass_version', FREEPASS_VERSION);
        // The site-wide secret from 0.1.x is not used any more.
        delete_option('freepass_secret');
    }
}

new Freepass_2FA();

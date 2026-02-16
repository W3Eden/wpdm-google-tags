<?php
/**
 * Settings - Centralized settings management
 *
 * Stores all plugin settings in a single option array.
 * Provides migration from v1.0 individual options.
 *
 * @package WPDMGoogleTags
 * @since 2.0.0
 */

namespace WPDMGoogleTags;

defined('ABSPATH') || exit;

class Settings {

    private static string $optionKey = '__wpdm_gtag_settings';
    private static ?array $cache = null;

    /**
     * Default settings.
     */
    private static array $defaults = [
        'gtm_id'          => '',
        'gtm_auth'        => '',
        'gtm_preview'     => '',
        'ga4_id'          => '',
        'mp_secret'       => '',
        'track_view_item' => 1,
        'track_cart'      => 1,
        'track_checkout'  => 1,
        'track_purchase'  => 1,
        'track_download'  => 1,
        'track_refund'    => 1,
        'track_signup'    => 1,
        'track_login'     => 0,
        'debug_mode'      => 0,
    ];

    /**
     * Register settings hooks.
     */
    public static function register(): void {
        if (is_admin()) {
            add_filter('add_wpdm_settings_tab', [__CLASS__, 'addSettingsTab']);
        }
    }

    /**
     * Add settings tab to WPDM settings.
     */
    public static function addSettingsTab(array $tabs): array {
        $tabs['wpdm-google-tags'] = \WPDM\Admin\Menu\Settings::createMenu(
            'wpdm-google-tags',
            'Google Tags',
            [__CLASS__, 'renderSettings'],
            'fas fa-tags'
        );
        return $tabs;
    }

    /**
     * Render the settings page.
     */
    public static function renderSettings(): void {
        if (wpdm_query_var('save_gtm_settings', 'int') === 1) {
            self::save();
            die('Settings Saved Successfully.');
        }

        $settings = self::getAll();
        include WPDM_GTAG_DIR . 'tpls/settings.php';
    }

    /**
     * Save settings from POST data.
     */
    private static function save(): void {
        $raw = isset($_POST['__wpdm_gtag']) ? $_POST['__wpdm_gtag'] : [];
        if (!is_array($raw)) return;

        $settings = self::getAll();

        // Text fields
        foreach (['gtm_id', 'gtm_auth', 'gtm_preview', 'ga4_id', 'mp_secret'] as $key) {
            if (isset($raw[$key])) {
                $settings[$key] = sanitize_text_field(wp_unslash($raw[$key]));
            }
        }

        // Checkbox fields (default to 0 if not present)
        foreach (['track_view_item', 'track_cart', 'track_checkout', 'track_purchase', 'track_download', 'track_refund', 'track_signup', 'track_login', 'debug_mode'] as $key) {
            $settings[$key] = isset($raw[$key]) ? 1 : 0;
        }

        update_option(self::$optionKey, $settings);
        self::$cache = $settings;
    }

    /**
     * Get all settings with defaults.
     */
    public static function getAll(): array {
        if (self::$cache !== null) return self::$cache;

        $settings = get_option(self::$optionKey, []);
        self::$cache = array_merge(self::$defaults, is_array($settings) ? $settings : []);

        return self::$cache;
    }

    /**
     * Get a single setting value.
     */
    public static function get(string $key, $default = null) {
        $settings = self::getAll();
        return $settings[$key] ?? $default ?? (self::$defaults[$key] ?? null);
    }

    /**
     * Check if a specific tracking feature is enabled.
     */
    public static function isEnabled(string $key): bool {
        return (bool) self::get($key, 0);
    }

    /**
     * Migrate v1.0 individual options to single settings array.
     */
    public static function migrate(): void {
        $existing = get_option(self::$optionKey);
        if ($existing && is_array($existing)) return; // Already migrated

        $settings = [
            'gtm_id'          => get_option('__wpdm_gtag_id', ''),
            'gtm_auth'        => get_option('__wpdm_gtm_auth', ''),
            'gtm_preview'     => get_option('__wpdm_gtm_preview', ''),
            'ga4_id'          => '',
            'mp_secret'       => '',
            'track_view_item' => 1,
            'track_cart'      => 1,
            'track_checkout'  => 1,
            'track_purchase'  => (int) get_option('__wpdm_gtag_purchase', 1),
            'track_download'  => (int) get_option('__wpdm_gtag_dle', 1),
            'track_refund'    => 1,
            'track_signup'    => (int) get_option('__wpdm_gtag_signup', 0),
            'track_login'     => (int) get_option('__wpdm_gtag_login', 0),
            'debug_mode'      => 0,
        ];

        update_option(self::$optionKey, $settings);
        self::$cache = $settings;
    }
}

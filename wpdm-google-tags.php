<?php
/**
 * Plugin Name:  WPDM - Google Tags
 * Plugin URI: https://www.wpdownloadmanager.com/
 * Description: GA4 e-commerce tracking & Google Tag Manager integration for WordPress Download Manager
 * Author: Download Manager
 * Version: 2.0.1
 * Author URI: https://www.wpdownloadmanager.com/
 * Update URI: wpdm-google-tags
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPDM_GTAG_VERSION', '2.0.1');
define('WPDM_GTAG_DIR', plugin_dir_path(__FILE__));
define('WPDM_GTAG_URL', plugin_dir_url(__FILE__));

/**
 * PSR-4 style autoloader for WPDMGoogleTags namespace.
 */
spl_autoload_register(function ($class) {
    $prefix = 'WPDMGoogleTags\\';
    $baseDir = WPDM_GTAG_DIR . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Run settings migration on activation.
 */
register_activation_hook(__FILE__, function () {
    \WPDMGoogleTags\Settings::migrate();
});

/**
 * Initialize the plugin after WPDM core is loaded.
 */
if (defined('WPDM_VERSION')) {

    add_action('plugins_loaded', function () {

        // Settings (admin tab)
        \WPDMGoogleTags\Settings::register();

        // GTM container embed (head + body + dataLayer render)
        $gtmEmbed = new \WPDMGoogleTags\GTMEmbed();
        $gtmEmbed->register();

        // Enqueue client-side JS on frontend
        add_action('wp_enqueue_scripts', function () {
            $settings = \WPDMGoogleTags\Settings::getAll();
            $gtmId = $settings['gtm_id'] ?? '';
            if (!$gtmId) return;

            wp_enqueue_script(
                'wpdm-gtag-events',
                WPDM_GTAG_URL . 'assets/js/gtm-events.min.js',
                ['jquery'],
                WPDM_GTAG_VERSION,
                true
            );

            wp_localize_script('wpdm-gtag-events', 'wpdmGtagConfig', [
                'debug'    => !empty($settings['debug_mode']),
                'currency' => \WPDMGoogleTags\Helper\ProductData::getCurrency(),
            ]);
        });

        // --- Trackers ---

        // View Item (single package page) — always available with core WPDM
        $viewItemTracker = new \WPDMGoogleTags\Tracker\ViewItemTracker();
        $viewItemTracker->register();

        // Download Tracker — always available with core WPDM
        $downloadTracker = new \WPDMGoogleTags\Tracker\DownloadTracker();
        $downloadTracker->register();

        // User Tracker — always available
        $userTracker = new \WPDMGoogleTags\Tracker\UserTracker();
        $userTracker->register();

        // E-commerce trackers — only when Premium Packages is active
        if (defined('WPDMPP_VERSION')) {
            $cartTracker = new \WPDMGoogleTags\Tracker\CartTracker();
            $cartTracker->register();

            $checkoutTracker = new \WPDMGoogleTags\Tracker\CheckoutTracker();
            $checkoutTracker->register();

            $orderTracker = new \WPDMGoogleTags\Tracker\OrderTracker();
            $orderTracker->register();
        }

    });

    /**
     * Auto-updater integration.
     */
    add_filter('update_plugins_wpdm-google-tags', function ($update, $plugin_data, $plugin_file, $locales) {
        $id = basename(__DIR__);
        $latest_versions = WPDM()->updater->getLatestVersions();
        $latest_version = wpdm_valueof($latest_versions, $id);
        $access_token = wpdm_access_token();

        return [
            'id'      => $id,
            'slug'    => $id,
            'url'     => $plugin_data['PluginURI'],
            'tested'  => true,
            'version' => $latest_version,
            'package' => "https://www.wpdownloadmanager.com/?wpdmdl=208184",
        ];
    }, 10, 4);
}

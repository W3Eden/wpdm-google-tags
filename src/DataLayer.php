<?php
/**
 * DataLayer - Server-side event queue
 *
 * Manages a session-based queue of dataLayer events that persists across redirects.
 * Events are rendered as dataLayer.push() calls in wp_footer.
 *
 * @package WPDMGoogleTags
 * @since 2.0.0
 */

namespace WPDMGoogleTags;

defined('ABSPATH') || exit;

class DataLayer {

    private static string $sessionKey = 'wpdm_gtag_events';
    private static string $cookieKey = 'wpdm_gtag_queue';

    /**
     * Push an event to the session-based queue (for page-render events).
     */
    public static function push(array $event): void {
        if (empty($event)) return;

        $settings = Settings::getAll();
        if (!empty($settings['debug_mode'])) {
            error_log('[WPDM GTags] Event: ' . wp_json_encode($event));
        }

        $events = [];
        if (class_exists('\WPDM\__\Session')) {
            $events = \WPDM\__\Session::get(self::$sessionKey) ?: [];
        }
        $events[] = $event;

        if (class_exists('\WPDM\__\Session')) {
            \WPDM\__\Session::set(self::$sessionKey, $events, 300);
        }
    }

    /**
     * Push an event via cookie (for non-page contexts like downloads).
     * The cookie is read client-side on the next page load.
     */
    public static function pushViaCookie(array $event): void {
        if (empty($event)) return;

        $settings = Settings::getAll();
        if (!empty($settings['debug_mode'])) {
            error_log('[WPDM GTags] Cookie Event: ' . wp_json_encode($event));
        }

        $existing = isset($_COOKIE[self::$cookieKey])
            ? json_decode(wp_unslash($_COOKIE[self::$cookieKey]), true)
            : [];

        if (!is_array($existing)) $existing = [];
        $existing[] = $event;

        setcookie(
            self::$cookieKey,
            wp_json_encode($existing),
            time() + 300,
            '/',
            '',
            is_ssl(),
            false // httpOnly must be false so JS can read it
        );
    }

    /**
     * Render queued events as <script> tags.
     * Called in wp_footer. Clears queue after output.
     */
    public static function render(): void {
        $events = [];
        if (class_exists('\WPDM\__\Session')) {
            $events = \WPDM\__\Session::get(self::$sessionKey) ?: [];
        }

        if (empty($events)) return;

        echo "\n<script>\nwindow.dataLayer = window.dataLayer || [];\n";
        foreach ($events as $event) {
            if (isset($event['ecommerce'])) {
                echo "dataLayer.push({ ecommerce: null });\n";
            }
            echo 'dataLayer.push(' . wp_json_encode($event, JSON_UNESCAPED_UNICODE) . ");\n";
        }
        echo "</script>\n";

        if (class_exists('\WPDM\__\Session')) {
            \WPDM\__\Session::clear(self::$sessionKey);
        }
    }
}

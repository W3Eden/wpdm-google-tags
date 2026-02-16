<?php
/**
 * DownloadTracker - Server-side download event tracking
 *
 * Pushes GA4 file_download event when a download starts.
 * Uses cookie-based queue since the download handler terminates PHP before wp_footer.
 * Optionally sends via GA4 Measurement Protocol for reliability.
 *
 * @package WPDMGoogleTags\Tracker
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Settings;
use WPDMGoogleTags\MeasurementProtocol;
use WPDMGoogleTags\Helper\ProductData;

defined('ABSPATH') || exit;

class DownloadTracker {

    public function register(): void {
        if (!Settings::isEnabled('track_download')) return;

        add_action('wpdm_onstart_download', [$this, 'onDownload']);
    }

    /**
     * GA4 file_download event.
     *
     * Hook: wpdm_onstart_download
     * Param: $package (array) — full package data with ID, post_title, etc.
     * Location: download-manager/src/wpdm-start-download.php:30
     *
     * Note: After this hook, PHP streams the file and exits. There is no wp_footer.
     * We use the cookie queue so the event fires on the next page load.
     */
    public function onDownload($package): void {
        if (!is_array($package) || empty($package['ID'])) return;

        $pid = (int) $package['ID'];
        $basePrice = (float) get_post_meta($pid, '__wpdm_base_price', true);
        $title = $package['post_title'] ?? get_the_title($pid);

        $event = [
            'event'        => 'file_download',
            'file_name'    => $title,
            'file_id'      => (string) $pid,
            'content_type' => $basePrice > 0 ? 'premium' : 'free',
        ];

        // Cookie-based queue — fires on next page load
        DataLayer::pushViaCookie($event);

        // Also send via Measurement Protocol for reliability (if configured)
        MeasurementProtocol::send([
            'name' => 'file_download',
            'params' => [
                'file_name'    => $title,
                'file_id'      => (string) $pid,
                'content_type' => $basePrice > 0 ? 'premium' : 'free',
            ],
        ]);
    }
}

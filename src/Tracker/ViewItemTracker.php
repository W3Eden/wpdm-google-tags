<?php
/**
 * ViewItemTracker - Tracks single package page views
 *
 * Pushes GA4 view_item event when a single wpdmpro post is viewed.
 *
 * @package WPDMGoogleTags\Tracker
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Settings;
use WPDMGoogleTags\Helper\ProductData;

defined('ABSPATH') || exit;

class ViewItemTracker {

    public function register(): void {
        if (!Settings::isEnabled('track_view_item')) return;

        add_action('wp', [$this, 'onViewItem']);
    }

    /**
     * Push view_item when a single package page is viewed.
     */
    public function onViewItem(): void {
        if (!is_singular('wpdmpro')) return;

        $pid = get_the_ID();
        if (!$pid) return;

        $gaItem = ProductData::fromProduct($pid);
        if (empty($gaItem)) return;

        DataLayer::push([
            'event' => 'view_item',
            'ecommerce' => [
                'currency' => ProductData::getCurrency(),
                'value'    => (float) $gaItem['price'],
                'items'    => [$gaItem],
            ],
        ]);
    }
}

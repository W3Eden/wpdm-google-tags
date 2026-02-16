<?php
/**
 * CartTracker - Tracks cart events
 *
 * Pushes GA4 add_to_cart, remove_from_cart, and view_cart events.
 *
 * @package WPDMGoogleTags\Tracker
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Settings;
use WPDMGoogleTags\Helper\ProductData;

defined('ABSPATH') || exit;

class CartTracker {

    public function register(): void {
        if (!Settings::isEnabled('track_cart')) return;

        add_action('wpdmpp_item_added_to_cart', [$this, 'onAddToCart'], 10, 3);
        add_action('wpdmpp_item_removed_from_cart', [$this, 'onRemoveFromCart'], 10, 2);
        add_action('wpdmpp_dynamic_item_added', [$this, 'onDynamicItemAdded'], 10, 3);
        add_action('wpdmpp_before_cart', [$this, 'onViewCart']);
    }

    /**
     * GA4 add_to_cart event.
     *
     * Hook: wpdmpp_item_added_to_cart
     * Params: $productId (int), $item (CartItem), $cart (Cart)
     */
    public function onAddToCart(int $productId, $item, $cart): void {
        $overrides = [
            'quantity' => $item->getQuantity(),
        ];

        $unitPrice = $item->getPrice();
        if (method_exists($item, 'getGigsCost')) {
            $unitPrice += $item->getGigsCost();
        }
        $overrides['price'] = (float) $unitPrice;

        $gaItem = ProductData::fromProduct($productId, $overrides);
        if (empty($gaItem)) return;

        DataLayer::push([
            'event' => 'add_to_cart',
            'ecommerce' => [
                'currency' => ProductData::getCurrency(),
                'value'    => (float) ($unitPrice * $item->getQuantity()),
                'items'    => [$gaItem],
            ],
        ]);
    }

    /**
     * GA4 remove_from_cart event.
     *
     * Hook: wpdmpp_item_removed_from_cart
     * Params: $productId (int), $cart (Cart)
     */
    public function onRemoveFromCart(int $productId, $cart): void {
        $gaItem = ProductData::fromProduct($productId);
        if (empty($gaItem)) return;

        DataLayer::push([
            'event' => 'remove_from_cart',
            'ecommerce' => [
                'currency' => ProductData::getCurrency(),
                'items'    => [$gaItem],
            ],
        ]);
    }

    /**
     * GA4 add_to_cart for dynamic items (subscriptions, etc.)
     *
     * Hook: wpdmpp_dynamic_item_added
     * Params: $itemId (int|string), $item (CartItem), $cart (Cart)
     */
    public function onDynamicItemAdded($itemId, $item, $cart): void {
        $gaItem = [
            'item_id'   => (string) $itemId,
            'item_name' => method_exists($item, 'getProductName') ? $item->getProductName() : (string) $itemId,
            'price'     => (float) $item->getPrice(),
            'quantity'  => $item->getQuantity(),
        ];

        DataLayer::push([
            'event' => 'add_to_cart',
            'ecommerce' => [
                'currency' => ProductData::getCurrency(),
                'value'    => (float) ($item->getPrice() * $item->getQuantity()),
                'items'    => [$gaItem],
            ],
        ]);
    }

    /**
     * GA4 view_cart event.
     *
     * Hook: wpdmpp_before_cart (fires in cart.php before checkout renders)
     */
    public function onViewCart(): void {
        if (!class_exists('\WPDMPP\Cart\CartService')) return;

        $cartService = \WPDMPP\Cart\CartService::instance();
        $cart = $cartService->getCart();

        if (!$cart || count($cart) === 0) return;

        DataLayer::push([
            'event' => 'view_cart',
            'ecommerce' => [
                'currency' => ProductData::getCurrency(),
                'value'    => (float) $cartService->getTotal(),
                'items'    => ProductData::fromCart($cart),
            ],
        ]);
    }
}

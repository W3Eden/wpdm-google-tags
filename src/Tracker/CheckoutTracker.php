<?php
/**
 * CheckoutTracker - Tracks checkout events
 *
 * Pushes GA4 begin_checkout event and enqueues client-side JS for add_payment_info.
 *
 * @package WPDMGoogleTags\Tracker
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Settings;
use WPDMGoogleTags\Helper\ProductData;

defined('ABSPATH') || exit;

class CheckoutTracker {

    private bool $checkoutTracked = false;

    public function register(): void {
        if (!Settings::isEnabled('track_checkout')) return;

        // begin_checkout fires when checkout form renders
        // wpdmpp_after_cart fires in cart.php just before checkout template is included
        add_action('wpdmpp_after_cart', [$this, 'onBeginCheckout']);

        // Enqueue JS for payment method tracking + localize cart data
        add_action('wp_enqueue_scripts', [$this, 'enqueueCheckoutData']);
    }

    /**
     * GA4 begin_checkout event.
     *
     * Hook: wpdmpp_after_cart (fires after cart, before checkout form)
     */
    public function onBeginCheckout(): void {
        // Prevent duplicate firing
        if ($this->checkoutTracked) return;
        $this->checkoutTracked = true;

        if (!class_exists('\WPDMPP\Cart\CartService')) return;

        $cartService = \WPDMPP\Cart\CartService::instance();
        $cart = $cartService->getCart();

        if (!$cart || count($cart) === 0) return;

        $couponCode = '';
        if (method_exists($cart, 'getCouponCode')) {
            $couponCode = $cart->getCouponCode();
        }

        $event = [
            'event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => ProductData::getCurrency(),
                'value'    => (float) $cartService->getTotal(),
                'items'    => ProductData::fromCart($cart),
            ],
        ];

        if ($couponCode) {
            $event['ecommerce']['coupon'] = $couponCode;
        }

        DataLayer::push($event);
    }

    /**
     * Localize cart data for client-side payment tracking.
     */
    public function enqueueCheckoutData(): void {
        if (!class_exists('\WPDMPP\Cart\CartService')) return;

        $cartService = \WPDMPP\Cart\CartService::instance();
        $cart = $cartService->getCart();

        if (!$cart || count($cart) === 0) return;

        wp_localize_script('wpdm-gtag-events', 'wpdmGtagCheckout', [
            'currency'  => ProductData::getCurrency(),
            'cartTotal' => (float) $cartService->getTotal(),
            'cartItems' => ProductData::fromCart($cart),
        ]);
    }
}

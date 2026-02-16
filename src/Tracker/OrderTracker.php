<?php
/**
 * OrderTracker - Tracks purchase, refund, and renewal events
 *
 * Pushes GA4 purchase and refund events from order lifecycle hooks.
 *
 * @package WPDMGoogleTags\Tracker
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Settings;
use WPDMGoogleTags\Helper\ProductData;
use WPDMGoogleTags\MeasurementProtocol;

defined('ABSPATH') || exit;

class OrderTracker {

    public function register(): void {
        if (Settings::isEnabled('track_purchase')) {
            add_action('wpdmpp_order_completed', [$this, 'onPurchase']);
            add_action('wpdmpp_order_renewed', [$this, 'onRenewal']);
        }

        if (Settings::isEnabled('track_refund')) {
            add_action('wpdmpp_order_refunded', [$this, 'onRefund'], 10, 3);
        }
    }

    /**
     * GA4 purchase event.
     *
     * Hook: wpdmpp_order_completed
     * Param: $orderId (string)
     * Location: OrderService.php:330
     */
    public function onPurchase(string $orderId): void {
        $order = $this->getOrder($orderId);
        if (!$order) return;

        $items = ProductData::fromOrder($order);
        if (empty($items)) return;

        $event = [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => $order->getOrderId(),
                'value'          => (float) $order->getTotal(),
                'tax'            => (float) $order->getTax(),
                'currency'       => $order->getCurrencyCode() ?: ProductData::getCurrency(),
                'items'          => $items,
            ],
        ];

        $couponCode = $order->getCouponCode();
        if ($couponCode) {
            $event['ecommerce']['coupon'] = $couponCode;
        }

        $discount = $order->getTotalDiscount();
        if ($discount > 0) {
            $event['ecommerce']['discount'] = (float) $discount;
        }

        DataLayer::push($event);
    }

    /**
     * GA4 purchase event for renewals.
     *
     * Hook: wpdmpp_order_renewed
     * Param: $orderId (string)
     * Location: OrderService.php:320, 402
     */
    public function onRenewal(string $orderId): void {
        $order = $this->getOrder($orderId);
        if (!$order) return;

        $items = ProductData::fromOrder($order);
        if (empty($items)) return;

        DataLayer::push([
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => $order->getOrderId() . '_R' . time(),
                'value'          => (float) $order->getTotal(),
                'tax'            => (float) $order->getTax(),
                'currency'       => $order->getCurrencyCode() ?: ProductData::getCurrency(),
                'items'          => $items,
                'is_renewal'     => true,
            ],
        ]);
    }

    /**
     * GA4 refund event.
     *
     * Hook: wpdmpp_order_refunded
     * Params: $orderId (string), $amount (float), $reason (string)
     * Location: OrderService.php:513
     */
    public function onRefund(string $orderId, float $amount, string $reason): void {
        $order = $this->getOrder($orderId);
        if (!$order) return;

        $event = [
            'event' => 'refund',
            'ecommerce' => [
                'transaction_id' => $order->getOrderId(),
                'value'          => (float) $amount,
                'currency'       => $order->getCurrencyCode() ?: ProductData::getCurrency(),
            ],
        ];

        // Full refund includes items
        if ($amount >= $order->getTotal()) {
            $event['ecommerce']['items'] = ProductData::fromOrder($order);
        }

        // Refunds happen in admin — push to session for admin page render
        DataLayer::push($event);

        // Also send via Measurement Protocol for reliability (admin may not render footer)
        MeasurementProtocol::send([
            'name' => 'refund',
            'params' => [
                'transaction_id' => $order->getOrderId(),
                'value'          => (float) $amount,
                'currency'       => $order->getCurrencyCode() ?: ProductData::getCurrency(),
            ],
        ]);
    }

    /**
     * Get Order entity from OrderService.
     */
    private function getOrder(string $orderId) {
        if (!class_exists('\WPDMPP\Order\OrderService')) return null;

        return \WPDMPP\Order\OrderService::instance()->getOrder($orderId);
    }
}

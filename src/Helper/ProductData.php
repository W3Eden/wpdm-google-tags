<?php
/**
 * ProductData - GA4 item array builder
 *
 * Builds GA4-compliant items[] arrays from WPDM packages, Cart objects, and Order objects.
 *
 * @package WPDMGoogleTags\Helper
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Helper;

defined('ABSPATH') || exit;

class ProductData {

    /**
     * Build a GA4 item array from a WPDM package/product.
     *
     * @param int   $productId  Post ID of the wpdmpro package
     * @param array $overrides  Override specific fields (price, quantity, etc.)
     * @return array GA4 item or empty array if product not found
     */
    public static function fromProduct(int $productId, array $overrides = []): array {
        $post = get_post($productId);
        if (!$post) return [];

        $basePrice = (float) get_post_meta($productId, '__wpdm_base_price', true);
        $salesPrice = (float) get_post_meta($productId, '__wpdm_sales_price', true);

        // Check sale expiry
        $saleExpire = get_post_meta($productId, '__wpdm_sales_price_expire', true);
        if ($salesPrice > 0 && $saleExpire && (int) $saleExpire < time()) {
            $salesPrice = 0;
        }

        $effectivePrice = ($salesPrice > 0) ? $salesPrice : $basePrice;

        $item = [
            'item_id'   => (string) $productId,
            'item_name' => $post->post_title,
            'price'     => (float) $effectivePrice,
            'quantity'  => 1,
        ];

        // Category
        $categories = wp_get_post_terms($productId, 'wpdmcategory', ['fields' => 'names']);
        if (!empty($categories) && !is_wp_error($categories)) {
            $item['item_category'] = $categories[0];
            if (isset($categories[1])) {
                $item['item_category2'] = $categories[1];
            }
        }

        // SKU / product code
        $sku = get_post_meta($productId, '__wpdm_product_code', true);
        if ($sku) {
            $item['item_id'] = $sku;
        }

        // Discount
        if ($salesPrice > 0 && $salesPrice < $basePrice) {
            $item['discount'] = round($basePrice - $salesPrice, 2);
        }

        return array_merge($item, $overrides);
    }

    /**
     * Build GA4 items array from a Cart object.
     *
     * @param \WPDMPP\Cart\Cart $cart
     * @return array
     */
    public static function fromCart($cart): array {
        $items = [];
        foreach ($cart as $cartItem) {
            $overrides = [
                'quantity' => $cartItem->getQuantity(),
            ];

            $unitPrice = $cartItem->getPrice();
            if (method_exists($cartItem, 'getGigsCost')) {
                $unitPrice += $cartItem->getGigsCost();
            }
            $overrides['price'] = (float) $unitPrice;

            // License info
            if (method_exists($cartItem, 'getLicense')) {
                $license = $cartItem->getLicense();
                if (!empty($license) && is_array($license)) {
                    $licenseName = $license['info']['name'] ?? ($license['id'] ?? '');
                    if ($licenseName) {
                        $overrides['item_variant'] = $licenseName;
                    }
                }
            }

            $item = self::fromProduct($cartItem->getProductId(), $overrides);
            if (!empty($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Build GA4 items array from an Order object.
     *
     * @param \WPDMPP\Order\Order $order
     * @return array
     */
    public static function fromOrder($order): array {
        $items = [];
        foreach ($order->getItems() as $orderItem) {
            $overrides = [
                'quantity' => $orderItem->getQuantity(),
            ];

            $unitPrice = $orderItem->getPrice();
            if (method_exists($orderItem, 'getGigsCost')) {
                $unitPrice += $orderItem->getGigsCost();
            }
            $overrides['price'] = (float) $unitPrice;

            // License info
            if (method_exists($orderItem, 'getLicenseName')) {
                $licenseName = $orderItem->getLicenseName();
                if ($licenseName) {
                    $overrides['item_variant'] = $licenseName;
                }
            }

            // Coupon discount
            if (method_exists($orderItem, 'getCouponDiscount')) {
                $couponDiscount = $orderItem->getCouponDiscount();
                if ($couponDiscount > 0) {
                    $overrides['coupon'] = $order->getCouponCode();
                    $overrides['discount'] = (float) $couponDiscount;
                }
            }

            $item = self::fromProduct($orderItem->getProductId(), $overrides);
            if (!empty($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Build a GA4 item from a raw package array (used by download tracker).
     *
     * @param array $package  WPDM package data array with ID, post_title, etc.
     * @return array
     */
    public static function fromPackageArray(array $package): array {
        $pid = $package['ID'] ?? 0;
        if (!$pid) return [];

        $basePrice = (float) get_post_meta($pid, '__wpdm_base_price', true);

        $item = [
            'item_id'   => (string) $pid,
            'item_name' => $package['post_title'] ?? get_the_title($pid),
            'price'     => $basePrice,
            'quantity'  => 1,
        ];

        $sku = get_post_meta($pid, '__wpdm_product_code', true);
        if ($sku) {
            $item['item_id'] = $sku;
        }

        $categories = wp_get_post_terms($pid, 'wpdmcategory', ['fields' => 'names']);
        if (!empty($categories) && !is_wp_error($categories)) {
            $item['item_category'] = $categories[0];
        }

        return $item;
    }

    /**
     * Get the store currency code.
     *
     * @return string ISO 4217 currency code
     */
    public static function getCurrency(): string {
        if (function_exists('get_wpdmpp_option')) {
            $code = get_wpdmpp_option('currency', 'USD');
            return $code ?: 'USD';
        }
        return 'USD';
    }
}

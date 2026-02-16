# WPDM Google Tags — Implementation Guide

## Current State (v1.0.0)

The existing plugin provides basic GTM container injection and simple custom event tracking:

- GTM container embed (head + noscript)
- Environment auth/preview support
- Custom events: `WPDM.newSignup`, `WPDM.userLogin`, `WPDM.newDownload`
- Purchase/renewal toggle in settings (no implementation)
- Client-side click-based download tracking (unreliable)
- Dead code: `pushTogtag()` references a Sendinblue/Brevo API that was never wired up

### Issues to Fix

| Issue | Location | Problem |
|-------|----------|---------|
| XSS vulnerability | `embedCode()` | Unescaped `<?= $gtm_id ?>`, `<?= $gtm_auth ?>`, `<?= $gtm_preview ?>` |
| Dead code | `pushTogtag()` | References non-existent Sendinblue classes |
| Non-standard events | `footerCode()` | Custom event names instead of GA4 recommended events |
| Unreliable tracking | `footerCode()` | Download tracking via click handler, not server-side |
| No e-commerce | — | No GA4 e-commerce events (add_to_cart, purchase, etc.) |
| Console.log spam | `footerCode()` | Debug logging left in production code |
| Individual options | `settings()` | Each setting stored as separate `update_option()` call |

---

## Target State (v2.0.0)

Full GA4 e-commerce integration with both server-side and client-side tracking:

### GA4 Event Coverage

| GA4 Event | Trigger | Priority |
|-----------|---------|----------|
| `view_item` | Single package page view | P1 |
| `add_to_cart` | Item added to cart | P1 |
| `remove_from_cart` | Item removed from cart | P2 |
| `view_cart` | Cart page loaded | P2 |
| `begin_checkout` | Checkout form rendered | P1 |
| `add_payment_info` | Payment method selected | P3 |
| `purchase` | Order completed | P1 |
| `refund` | Order refunded | P2 |
| `file_download` | Download started (server-side) | P1 |
| `sign_up` | New user registered | P2 |
| `login` | User logged in | P3 |

---

## Architecture

### File Structure

```
wpdm-google-tags/
├── wpdm-google-tags.php          # Main plugin file (rewrite)
├── src/
│   ├── DataLayer.php             # Server-side dataLayer queue
│   ├── GTMEmbed.php              # GTM container embed (head + body)
│   ├── Settings.php              # Settings page (single option)
│   ├── Tracker/
│   │   ├── CartTracker.php       # Cart events (add, remove, view, coupon)
│   │   ├── CheckoutTracker.php   # Checkout + payment events
│   │   ├── OrderTracker.php      # Purchase, refund, renewal
│   │   ├── DownloadTracker.php   # Server-side download tracking
│   │   └── UserTracker.php       # Sign-up, login events
│   └── Helper/
│       └── ProductData.php       # Build GA4 item arrays from WPDM packages
├── assets/
│   └── js/
│       └── gtm-events.js        # Client-side event helpers
├── tpls/
│   └── settings.php             # Settings template (rewrite)
└── IMPLEMENTATION-GUIDE.md       # This file
```

### Data Flow

```
WordPress Hook → Tracker class → DataLayer::push($event)
                                       ↓
                              wp_footer → DataLayer::render()
                                       ↓
                              <script>dataLayer.push({...})</script>
                                       ↓
                              GTM picks up → sends to GA4
```

Server-side hooks (cart add, order complete, download) push events to a session-based queue. On the next page render, `DataLayer::render()` outputs them as `dataLayer.push()` calls. Client-side events (payment method selection) push directly from JavaScript.

For AJAX-driven actions (new checkout cart editing, coupon apply), the REST API response is intercepted client-side to push events without page reload.

---

## Implementation Details

### 1. DataLayer Class

Manages a session-based event queue that persists across redirects.

```php
namespace WPDMGoogleTags;

class DataLayer {
    private static string $sessionKey = 'wpdm_gtag_events';

    /**
     * Push an event to the queue (server-side).
     * Events persist in session until rendered.
     */
    public static function push(array $event): void {
        $events = \WPDM\__\Session::get(self::$sessionKey) ?: [];
        $events[] = $event;
        \WPDM\__\Session::set(self::$sessionKey, $events, 300); // 5 min TTL
    }

    /**
     * Render queued events as <script> tags.
     * Called in wp_footer. Clears queue after output.
     */
    public static function render(): void {
        $events = \WPDM\__\Session::get(self::$sessionKey) ?: [];
        if (empty($events)) return;

        echo "\n<script>\nwindow.dataLayer = window.dataLayer || [];\n";
        foreach ($events as $event) {
            // Clear previous ecommerce object before each event
            if (isset($event['ecommerce'])) {
                echo "dataLayer.push({ ecommerce: null });\n";
            }
            echo 'dataLayer.push(' . wp_json_encode($event) . ");\n";
        }
        echo "</script>\n";

        \WPDM\__\Session::clear(self::$sessionKey);
    }
}
```

**Key design decisions:**
- Uses WPDM Session class (already available, handles device ID)
- 5-minute TTL ensures events aren't stale
- Clears `ecommerce` object before each push (GA4 requirement to prevent data bleed)
- `wp_json_encode` for safe output

---

### 2. ProductData Helper

Builds GA4 `items[]` arrays from WPDM packages. Used by every tracker.

```php
namespace WPDMGoogleTags\Helper;

class ProductData {

    /**
     * Build a GA4 item array from a WPDM package/product.
     */
    public static function fromProduct(int $productId, array $overrides = []): array {
        $post = get_post($productId);
        if (!$post) return [];

        $basePrice = (float) get_post_meta($productId, '__wpdm_base_price', true);
        $salesPrice = (float) get_post_meta($productId, '__wpdm_sales_price', true);
        $effectivePrice = $salesPrice > 0 ? $salesPrice : $basePrice;

        $categories = wp_get_post_terms($productId, 'wpdmcategory', ['fields' => 'names']);
        $category = !empty($categories) && !is_wp_error($categories) ? $categories[0] : '';

        $item = [
            'item_id'       => (string) $productId,
            'item_name'     => $post->post_title,
            'price'         => $effectivePrice,
            'quantity'      => 1,
        ];

        if ($category) {
            $item['item_category'] = $category;
        }

        $sku = get_post_meta($productId, '__wpdm_product_code', true);
        if ($sku) {
            $item['item_id'] = $sku;
            $item['item_variant'] = (string) $productId; // keep numeric ID as variant
        }

        if ($salesPrice > 0 && $salesPrice < $basePrice) {
            $item['discount'] = round($basePrice - $salesPrice, 2);
        }

        return array_merge($item, $overrides);
    }

    /**
     * Build GA4 items array from a Cart object.
     */
    public static function fromCart(\WPDMPP\Cart\Cart $cart): array {
        $items = [];
        foreach ($cart as $item) {
            $items[] = self::fromProduct($item->getProductId(), [
                'price'    => $item->getPrice() + $item->getGigsCost(),
                'quantity' => $item->getQuantity(),
            ]);
        }
        return $items;
    }

    /**
     * Build GA4 items array from an Order object.
     */
    public static function fromOrder(\WPDMPP\Order\Order $order): array {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = self::fromProduct($item->getProductId(), [
                'price'    => $item->getPrice() + $item->getGigsCost(),
                'quantity' => $item->getQuantity(),
            ]);
        }
        return $items;
    }

    /**
     * Get the store currency code.
     */
    public static function getCurrency(): string {
        return function_exists('get_wpdmpp_option')
            ? get_wpdmpp_option('currency', 'USD')
            : 'USD';
    }
}
```

---

### 3. Cart Tracker

Hooks into CartService events for `add_to_cart`, `remove_from_cart`, `view_cart`.

```php
namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Helper\ProductData;

class CartTracker {

    public function register(): void {
        add_action('wpdmpp_item_added_to_cart', [$this, 'onAddToCart'], 10, 3);
        add_action('wpdmpp_item_removed_from_cart', [$this, 'onRemoveFromCart'], 10, 2);
        add_action('wpdmpp_coupon_applied', [$this, 'onCouponApplied'], 10, 3);
    }
```

#### Hook → Event Mapping

| Hook | Parameters | GA4 Event |
|------|-----------|-----------|
| `wpdmpp_item_added_to_cart` | `$productId, $item, $cart` | `add_to_cart` |
| `wpdmpp_item_removed_from_cart` | `$productId, $cart` | `remove_from_cart` |
| `wpdmpp_coupon_applied` | `$code, $discount, $cart` | Custom data enrichment |

#### `add_to_cart` Event

```php
public function onAddToCart(int $productId, $item, $cart): void {
    $gaItem = ProductData::fromProduct($productId, [
        'price'    => $item->getPrice() + $item->getGigsCost(),
        'quantity' => $item->getQuantity(),
    ]);

    DataLayer::push([
        'event' => 'add_to_cart',
        'ecommerce' => [
            'currency' => ProductData::getCurrency(),
            'value'    => $item->getLineTotal(),
            'items'    => [$gaItem],
        ],
    ]);
}
```

#### `remove_from_cart` Event

```php
public function onRemoveFromCart(int $productId, $cart): void {
    $gaItem = ProductData::fromProduct($productId);

    DataLayer::push([
        'event' => 'remove_from_cart',
        'ecommerce' => [
            'currency' => ProductData::getCurrency(),
            'items'    => [$gaItem],
        ],
    ]);
}
```

#### `view_cart` Event

Push `view_cart` when the cart template renders. Hook into `wpdmpp_before_cart` (fires in `cart.php`):

```php
add_action('wpdmpp_before_cart', [$this, 'onViewCart']);

public function onViewCart(): void {
    $cartService = \WPDMPP\Cart\CartService::instance();
    $cart = $cartService->getCartObject();

    if (!$cart || count($cart) === 0) return;

    DataLayer::push([
        'event' => 'view_cart',
        'ecommerce' => [
            'currency' => ProductData::getCurrency(),
            'value'    => $cartService->getTotal(),
            'items'    => ProductData::fromCart($cart),
        ],
    ]);
}
```

---

### 4. Checkout Tracker

Fires `begin_checkout` and `add_payment_info`.

#### `begin_checkout` Event

Since there's no dedicated `wpdmpp_checkout_started` hook, use the `wpdmpp_before_cart` action combined with a check that cart is non-empty (the checkout template renders inside cart.php):

```php
public function register(): void {
    // begin_checkout fires when checkout form renders
    add_action('wpdmpp_before_checkout_form', [$this, 'onBeginCheckout']);
}

public function onBeginCheckout(): void {
    $cartService = \WPDMPP\Cart\CartService::instance();
    $cart = $cartService->getCartObject();

    if (!$cart || count($cart) === 0) return;

    DataLayer::push([
        'event' => 'begin_checkout',
        'ecommerce' => [
            'currency' => ProductData::getCurrency(),
            'value'    => $cartService->getTotal(),
            'items'    => ProductData::fromCart($cart),
        ],
    ]);
}
```

**Note:** If `wpdmpp_before_checkout_form` doesn't exist, add a `do_action('wpdmpp_before_checkout_form')` call at the top of `templates/checkout/checkout.php`. Alternatively, use the existing `wpdmpp_after_cart` hook which fires in cart.php just before the checkout template is included.

#### `add_payment_info` Event (Client-Side)

This must be handled in JavaScript since payment method selection happens in the browser:

```javascript
// In assets/js/gtm-events.js
document.addEventListener('change', function(e) {
    if (e.target.matches('input[name="payment_method"]')) {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push({ ecommerce: null });
        dataLayer.push({
            event: 'add_payment_info',
            ecommerce: {
                currency: wpdmGtag.currency,
                value: parseFloat(wpdmGtag.cartTotal),
                payment_type: e.target.value,
                items: wpdmGtag.cartItems
            }
        });
    }
});
```

Pass cart data to JS via `wp_localize_script`:

```php
wp_localize_script('wpdm-gtag-events', 'wpdmGtag', [
    'currency'  => ProductData::getCurrency(),
    'cartTotal' => $cartService->getTotal(),
    'cartItems' => ProductData::fromCart($cart),
]);
```

---

### 5. Order Tracker (Purchase, Refund, Renewal)

#### `purchase` Event

```php
public function register(): void {
    add_action('wpdmpp_order_completed', [$this, 'onPurchase']);
    add_action('wpdmpp_order_refunded', [$this, 'onRefund'], 10, 3);
    add_action('wpdmpp_order_renewed', [$this, 'onRenewal']);
}
```

**Hook:** `wpdmpp_order_completed`
**Parameter:** `$orderId` (string)
**Location:** `src/Order/OrderService.php:330`

```php
public function onPurchase(string $orderId): void {
    $orderService = \WPDMPP\Order\OrderService::instance();
    $order = $orderService->getOrder($orderId);
    if (!$order) return;

    $items = ProductData::fromOrder($order);

    DataLayer::push([
        'event' => 'purchase',
        'ecommerce' => [
            'transaction_id' => $order->getOrderId(),
            'value'          => $order->getTotal(),
            'tax'            => $order->getTax(),
            'currency'       => $order->getCurrencyCode(),
            'coupon'         => $order->getCouponCode() ?: null,
            'items'          => $items,
        ],
    ]);
}
```

**Important:** The `purchase` event fires server-side during payment processing. The customer is then redirected to the order confirmation page. The DataLayer session queue ensures the event is output on the confirmation page's `wp_footer`.

#### `refund` Event

**Hook:** `wpdmpp_order_refunded`
**Parameters:** `$orderId, $amount, $reason`
**Location:** `src/Order/OrderService.php:513`

```php
public function onRefund(string $orderId, float $amount, string $reason): void {
    $orderService = \WPDMPP\Order\OrderService::instance();
    $order = $orderService->getOrder($orderId);
    if (!$order) return;

    $event = [
        'event' => 'refund',
        'ecommerce' => [
            'transaction_id' => $order->getOrderId(),
            'value'          => $amount,
            'currency'       => $order->getCurrencyCode(),
        ],
    ];

    // Full refund includes items
    if ($amount >= $order->getTotal()) {
        $event['ecommerce']['items'] = ProductData::fromOrder($order);
    }

    DataLayer::push($event);
}
```

**Note:** Refunds happen in admin. The dataLayer push will be rendered on the next admin page load. If server-side tracking is needed (no browser), use the GA4 Measurement Protocol instead (see Section 9).

#### Order Renewal

**Hook:** `wpdmpp_order_renewed`
**Parameter:** `$orderId`
**Location:** `src/Order/OrderService.php:320, 402`

Treat renewals as a new `purchase` event with the same transaction_id suffixed with renewal timestamp:

```php
public function onRenewal(string $orderId): void {
    $orderService = \WPDMPP\Order\OrderService::instance();
    $order = $orderService->getOrder($orderId);
    if (!$order) return;

    DataLayer::push([
        'event' => 'purchase',
        'ecommerce' => [
            'transaction_id' => $order->getOrderId() . '_R' . time(),
            'value'          => $order->getTotal(),
            'tax'            => $order->getTax(),
            'currency'       => $order->getCurrencyCode(),
            'items'          => ProductData::fromOrder($order),
            'is_renewal'     => true, // custom dimension
        ],
    ]);
}
```

---

### 6. Download Tracker

Server-side tracking via the `wpdm_onstart_download` hook.

**Hook:** `wpdm_onstart_download`
**Parameter:** `$package` (array — full package data including `ID`, `post_title`, etc.)
**Location:** `download-manager/src/wpdm-start-download.php:30`

```php
public function register(): void {
    add_action('wpdm_onstart_download', [$this, 'onDownload']);
}

public function onDownload(array $package): void {
    $pid = $package['ID'];
    $basePrice = (float) get_post_meta($pid, '__wpdm_base_price', true);

    DataLayer::push([
        'event' => 'file_download',
        'file_name'     => $package['post_title'],
        'file_id'       => $pid,
        'content_type'  => $basePrice > 0 ? 'premium' : 'free',
        'ecommerce'     => [
            'items' => [ProductData::fromProduct($pid)],
        ],
    ]);
}
```

**Caveat:** Downloads often happen via PHP streaming (`readfile()`), so the page terminates after `wpdm_onstart_download`. The session-based DataLayer queue won't render because there's no `wp_footer`. Two solutions:

**Option A: Cookie-based queue** — Store events in a cookie. On next page load, JavaScript reads the cookie, pushes to dataLayer, and clears it.

**Option B: GA4 Measurement Protocol** — Send the event directly to GA4 from PHP using an HTTP request (no browser needed). Requires GA4 Measurement ID + API Secret.

**Recommended: Option A for simplicity, Option B for accuracy.**

#### Option A Implementation

```php
public function onDownload(array $package): void {
    $pid = $package['ID'];
    $basePrice = (float) get_post_meta($pid, '__wpdm_base_price', true);

    $event = [
        'event'        => 'file_download',
        'file_name'    => $package['post_title'],
        'file_id'      => $pid,
        'content_type' => $basePrice > 0 ? 'premium' : 'free',
    ];

    // Store in cookie since page will terminate (no wp_footer)
    $existing = isset($_COOKIE['wpdm_gtag_queue']) ? json_decode(stripslashes($_COOKIE['wpdm_gtag_queue']), true) : [];
    if (!is_array($existing)) $existing = [];
    $existing[] = $event;

    setcookie('wpdm_gtag_queue', wp_json_encode($existing), time() + 300, '/');
}
```

Client-side cookie reader (in `gtm-events.js`):

```javascript
(function() {
    var cookie = document.cookie.match(/wpdm_gtag_queue=([^;]+)/);
    if (!cookie) return;

    try {
        var events = JSON.parse(decodeURIComponent(cookie[1]));
        window.dataLayer = window.dataLayer || [];
        events.forEach(function(event) {
            dataLayer.push(event);
        });
        // Clear the cookie
        document.cookie = 'wpdm_gtag_queue=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    } catch(e) {}
})();
```

---

### 7. User Tracker

Replace existing custom events with GA4 recommended events.

| Current Event | GA4 Event | Notes |
|---------------|-----------|-------|
| `WPDM.newSignup` | `sign_up` | Standard GA4 event |
| `WPDM.userLogin` | `login` | Standard GA4 event |

```php
public function register(): void {
    add_action('user_register', [$this, 'onSignup']);
    add_action('wp_login', [$this, 'onLogin'], 10, 2);
}

public function onSignup(int $userId): void {
    // Cookie approach since redirect follows registration
    $this->pushViaCookie([
        'event'  => 'sign_up',
        'method' => 'email',
    ]);
}

public function onLogin(string $userName, \WP_User $user): void {
    $this->pushViaCookie([
        'event'  => 'login',
        'method' => 'email',
    ]);
}
```

---

### 8. view_item Event

Push `view_item` when a single package page is viewed.

```php
add_action('wp', [$this, 'onViewItem']);

public function onViewItem(): void {
    if (!is_singular('wpdmpro')) return;

    $pid = get_the_ID();
    $gaItem = ProductData::fromProduct($pid);
    if (empty($gaItem)) return;

    DataLayer::push([
        'event' => 'view_item',
        'ecommerce' => [
            'currency' => ProductData::getCurrency(),
            'value'    => $gaItem['price'],
            'items'    => [$gaItem],
        ],
    ]);
}
```

---

### 9. GA4 Measurement Protocol (Optional, for Server-Side Events)

For events that fire during non-browser requests (downloads, webhooks, cron), the GA4 Measurement Protocol sends data directly to Google.

```php
namespace WPDMGoogleTags;

class MeasurementProtocol {

    public static function send(array $event, ?string $clientId = null): bool {
        $measurementId = get_option('__wpdm_gtag_ga4_id');
        $apiSecret = get_option('__wpdm_gtag_mp_secret');

        if (!$measurementId || !$apiSecret) return false;

        // Use device ID as client_id
        if (!$clientId) {
            $clientId = class_exists('\WPDM\__\Session')
                ? \WPDM\__\Session::deviceID()
                : wp_generate_uuid4();
        }

        $payload = [
            'client_id' => $clientId,
            'events'    => [$event],
        ];

        $url = sprintf(
            'https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s',
            urlencode($measurementId),
            urlencode($apiSecret)
        );

        $response = wp_remote_post($url, [
            'body'    => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 5,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 300;
    }
}
```

Usage in DownloadTracker:

```php
public function onDownload(array $package): void {
    $pid = $package['ID'];

    // Try cookie-based approach first (for browser redirects)
    $this->pushViaCookie([...]);

    // Also send via Measurement Protocol for reliability
    MeasurementProtocol::send([
        'name' => 'file_download',
        'params' => [
            'file_name'    => $package['post_title'],
            'file_id'      => (string) $pid,
            'content_type' => ((float) get_post_meta($pid, '__wpdm_base_price', true)) > 0 ? 'premium' : 'free',
        ],
    ]);
}
```

---

### 10. GTM Embed (Rewrite)

Fix the XSS vulnerability in the current embed code.

```php
namespace WPDMGoogleTags;

class GTMEmbed {

    public function register(): void {
        add_action('wp_head', [$this, 'headScript'], 1);
        add_action('wp_body_open', [$this, 'bodyNoscript'], 1);
        add_action('wp_footer', [$this, 'bodyNoscriptFallback'], 1);
        add_action('wp_footer', [DataLayer::class, 'render'], 999);
    }

    public function headScript(): void {
        $gtmId = $this->getGtmId();
        if (!$gtmId) return;

        $auth = esc_js(get_option('__wpdm_gtm_auth', ''));
        $preview = esc_js(get_option('__wpdm_gtm_preview', ''));

        $envParams = '';
        if ($auth && $preview) {
            $envParams = "&gtm_auth={$auth}&gtm_preview={$preview}&gtm_cookies_win=x";
        }

        $gtmId = esc_js($gtmId);

        echo <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl+'{$envParams}';f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtmId}');</script>
<!-- End Google Tag Manager -->
HTML;
    }

    public function bodyNoscript(): void {
        $gtmId = $this->getGtmId();
        if (!$gtmId) return;

        $gtmId = esc_attr($gtmId);
        echo <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gtmId}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;
    }

    public function bodyNoscriptFallback(): void {
        // Fallback for themes that don't support wp_body_open
        if (!did_action('wp_body_open')) {
            $this->bodyNoscript();
        }
    }

    private function getGtmId(): string {
        $id = get_option('__wpdm_gtag_id', '');
        // Validate format: GTM-XXXXXXX
        return preg_match('/^GTM-[A-Z0-9]+$/', $id) ? $id : '';
    }
}
```

---

### 11. Settings (Rewrite)

Consolidate all settings into a single option array.

#### Option Key: `__wpdm_gtag_settings`

```php
[
    'gtm_id'          => 'GTM-XXXXXXX',
    'gtm_auth'        => '',
    'gtm_preview'     => '',
    'ga4_id'          => 'G-XXXXXXXXXX',    // For Measurement Protocol
    'mp_secret'       => '',                 // Measurement Protocol API Secret
    'track_view_item' => 1,
    'track_cart'      => 1,
    'track_checkout'  => 1,
    'track_purchase'  => 1,
    'track_download'  => 1,
    'track_refund'    => 1,
    'track_signup'    => 1,
    'track_login'     => 0,
    'debug_mode'      => 0,
]
```

#### Migration

On activation, migrate individual options to the new array:

```php
public function activate(): void {
    $existing = get_option('__wpdm_gtag_settings');
    if ($existing) return; // Already migrated

    $settings = [
        'gtm_id'          => get_option('__wpdm_gtag_id', ''),
        'gtm_auth'        => get_option('__wpdm_gtm_auth', ''),
        'gtm_preview'     => get_option('__wpdm_gtm_preview', ''),
        'track_download'  => (int) get_option('__wpdm_gtag_dle', 0),
        'track_signup'    => (int) get_option('__wpdm_gtag_signup', 0),
        'track_login'     => (int) get_option('__wpdm_gtag_login', 0),
        'track_purchase'  => (int) get_option('__wpdm_gtag_purchase', 0),
        // New defaults
        'track_view_item' => 1,
        'track_cart'      => 1,
        'track_checkout'  => 1,
        'track_refund'    => 1,
        'debug_mode'      => 0,
    ];

    update_option('__wpdm_gtag_settings', $settings);
}
```

---

### 12. Settings UI Template

#### Layout

```
┌─────────────────────────────────────────────────┐
│ Google Tag Manager                               │
│ ┌─────────────────────────────────────────────┐ │
│ │ GTM Container ID:  [GTM-XXXXXXX          ]  │ │
│ │ Environment Auth:  [                      ]  │ │
│ │ Environment Preview: [                    ]  │ │
│ └─────────────────────────────────────────────┘ │
│                                                  │
│ GA4 Measurement Protocol (Optional)              │
│ ┌─────────────────────────────────────────────┐ │
│ │ GA4 Measurement ID:  [G-XXXXXXXXXX       ]  │ │
│ │ API Secret:          [••••••••••          ]  │ │
│ │ ℹ️ Required for server-side download        │ │
│ │   tracking. Get from GA4 Admin > Data       │ │
│ │   Streams > Measurement Protocol API Secrets│ │
│ └─────────────────────────────────────────────┘ │
│                                                  │
│ E-commerce Tracking                              │
│ ┌─────────────────────────────────────────────┐ │
│ │ ☑ Track Product Views     (view_item)       │ │
│ │ ☑ Track Cart Events       (add/remove)      │ │
│ │ ☑ Track Checkout           (begin_checkout)  │ │
│ │ ☑ Track Purchases          (purchase)        │ │
│ │ ☑ Track Refunds            (refund)          │ │
│ └─────────────────────────────────────────────┘ │
│                                                  │
│ Other Events                                     │
│ ┌─────────────────────────────────────────────┐ │
│ │ ☑ Track Downloads          (file_download)   │ │
│ │ ☑ Track User Signups       (sign_up)         │ │
│ ☐ Track User Logins        (login)            │ │
│ └─────────────────────────────────────────────┘ │
│                                                  │
│ ☐ Debug Mode (console.log all events)           │
└─────────────────────────────────────────────────┘
```

---

## Hook Reference (Complete)

### Cart Lifecycle

| Hook | Parameters | File:Line | GA4 Event |
|------|-----------|-----------|-----------|
| `wpdmpp_item_added_to_cart` | `$productId, $item, $cart` | `CartService.php:195` | `add_to_cart` |
| `wpdmpp_item_removed_from_cart` | `$productId, $cart` | `CartService.php:261` | `remove_from_cart` |
| `wpdmpp_cart_cleared` | `$cartId` | `CartService.php:302` | — |
| `wpdmpp_coupon_applied` | `$code, $discount, $cart` | `CartService.php:362` | Enrich purchase data |
| `wpdmpp_coupon_removed` | `$cart` | `CartService.php:381` | — |
| `wpdmpp_dynamic_item_added` | `$itemId, $item, $cart` | `CartService.php:240` | `add_to_cart` |
| `wpdmpp_before_cart` | — | `cart.php:22` | `view_cart` |

### Order Lifecycle

| Hook | Parameters | File:Line | GA4 Event |
|------|-----------|-----------|-----------|
| `wpdmpp_new_order_created` | `$orderId` | `OrderService.php:172` | — |
| `wpdmpp_order_created` | `$order` (Order object) | `OrderService.php:173` | — |
| `wpdmpp_order_completed` | `$orderId` | `OrderService.php:330` | `purchase` |
| `wpdmpp_payment_completed` | `$orderId` | Multiple gateways | — (duplicate of above) |
| `wpdmpp_order_renewed` | `$orderId` | `OrderService.php:320,402` | `purchase` (renewal) |
| `wpdmpp_order_cancelled` | `$orderId, $reason` | `OrderService.php:440` | — |
| `wpdmpp_order_expired` | `$orderId` | `OrderService.php:467` | — |
| `wpdmpp_order_refunded` | `$orderId, $amount, $reason` | `OrderService.php:513` | `refund` |

### Download Lifecycle

| Hook | Parameters | File:Line | GA4 Event |
|------|-----------|-----------|-----------|
| `wpdm_onstart_download` | `$package` (array) | `wpdm-start-download.php:30` | `file_download` |
| `wpdm_after_insert_download_history` | `$pid, $uid` | `DownloadStats.php:72` | — |

### Recovery

| Hook | Parameters | File:Line | GA4 Event |
|------|-----------|-----------|-----------|
| `wpdmpp_recovery_link_clicked` | `$orderId` | `AbandonedOrderService.php:462` | Custom event |
| `wpdmpp_order_recovered` | `$orderId` | `AbandonedOrderService.php:337` | Custom event |

---

## AJAX Cart Events (Client-Side)

The new checkout uses REST API for cart modifications. These don't trigger PHP hooks because they're AJAX calls. Handle them client-side.

### In `assets/js/gtm-events.js`

Listen for AJAX responses from the cart REST API:

```javascript
(function($) {
    'use strict';

    // Intercept cart REST API calls for dataLayer events
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (!settings.url || !window.dataLayer) return;

        var url = settings.url;
        var method = settings.type || 'GET';

        // Cart item updated (PUT /wpdmpp/v1/cart/{id})
        if (url.match(/wpdmpp\/v1\/cart\/\d+/) && method === 'PUT') {
            // Quantity updated — no standard GA4 event, but could push custom
        }

        // Cart item removed (DELETE /wpdmpp/v1/cart/{id})
        if (url.match(/wpdmpp\/v1\/cart\/\d+/) && method === 'DELETE') {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    dataLayer.push({ ecommerce: null });
                    dataLayer.push({
                        event: 'remove_from_cart',
                        ecommerce: {
                            currency: wpdmGtag.currency,
                            items: [{ item_id: url.match(/\/cart\/(\d+)/)[1] }]
                        }
                    });
                }
            } catch(e) {}
        }

        // Coupon applied (POST /wpdmpp/v1/cart/coupon)
        if (url.match(/wpdmpp\/v1\/cart\/coupon/) && method === 'POST') {
            // No standard GA4 event for coupon, data included in purchase
        }
    });

})(jQuery);
```

---

## Debug Mode

When debug mode is enabled, wrap `DataLayer::push()` to also log events:

```php
public static function push(array $event): void {
    $settings = get_option('__wpdm_gtag_settings', []);

    if (!empty($settings['debug_mode'])) {
        error_log('[WPDM GTags] Event: ' . wp_json_encode($event));
    }

    // ... normal push logic
}
```

In the client-side JS, when debug is on:

```javascript
if (wpdmGtag.debug) {
    var originalPush = Array.prototype.push;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push = function() {
        console.log('[WPDM GTags]', arguments[0]);
        return originalPush.apply(this, arguments);
    };
}
```

---

## GTM Container Configuration Guide

Include this in plugin documentation for users setting up GTM:

### Required GA4 Tags

| Tag Name | Tag Type | Trigger |
|----------|----------|---------|
| GA4 Config | GA4 Configuration | All Pages |
| GA4 - view_item | GA4 Event | Custom Event: `view_item` |
| GA4 - add_to_cart | GA4 Event | Custom Event: `add_to_cart` |
| GA4 - begin_checkout | GA4 Event | Custom Event: `begin_checkout` |
| GA4 - purchase | GA4 Event | Custom Event: `purchase` |
| GA4 - file_download | GA4 Event | Custom Event: `file_download` |
| GA4 - refund | GA4 Event | Custom Event: `refund` |
| GA4 - sign_up | GA4 Event | Custom Event: `sign_up` |

### Custom Dimensions (Optional)

| Dimension | Scope | Parameter |
|-----------|-------|-----------|
| Content Type | Event | `content_type` (free/premium) |
| Is Renewal | Event | `is_renewal` (true/false) |
| File ID | Event | `file_id` |

---

## Implementation Order

### Phase 1: Core Infrastructure
1. Create `src/DataLayer.php`
2. Create `src/Helper/ProductData.php`
3. Rewrite `GTMEmbed` (fix XSS)
4. Rewrite Settings (single option, migration)
5. Rewrite settings UI template

### Phase 2: E-commerce Events (P1)
6. `view_item` — single package page
7. `add_to_cart` — CartService hook
8. `begin_checkout` — checkout render
9. `purchase` — order completed hook
10. `file_download` — download hook + cookie queue

### Phase 3: Secondary Events (P2)
11. `remove_from_cart` — CartService hook
12. `view_cart` — cart page hook
13. `refund` — order refunded hook
14. `sign_up` — user_register hook

### Phase 4: Advanced (P3)
15. `add_payment_info` — client-side JS
16. `login` — wp_login hook
17. Measurement Protocol for server-side events
18. AJAX cart event interception (client-side JS)
19. Debug mode

---

## Testing Checklist

| Test | Expected |
|------|----------|
| GTM container loads on frontend | `<script>` in `<head>`, `<noscript>` after `<body>` |
| GTM ID with special chars rejected | Validation blocks invalid IDs |
| View single package page | `view_item` in dataLayer with correct item data |
| Add item to cart | `add_to_cart` with item_name, price, quantity |
| Remove item from cart | `remove_from_cart` in dataLayer |
| View cart page | `view_cart` with all items and total value |
| Reach checkout form | `begin_checkout` with items and total |
| Select payment method | `add_payment_info` with payment_type |
| Complete purchase | `purchase` with transaction_id, value, items |
| Download a file | `file_download` on next page load (cookie queue) |
| Refund an order (admin) | `refund` with transaction_id and value |
| Register new user | `sign_up` on next page load |
| Debug mode enabled | Events logged to browser console and error_log |
| Premium Packages not installed | Plugin degrades gracefully (only tracks downloads, signups) |
| Empty GTM ID | No scripts injected |
| Multiple add_to_cart in sequence | Each fires separately, no data bleed |

---

## Graceful Degradation

The plugin should work with Download Manager core alone (without Premium Packages):

```php
// In main plugin file, conditionally register trackers
if (defined('WPDMPP_VERSION')) {
    (new CartTracker())->register();
    (new CheckoutTracker())->register();
    (new OrderTracker())->register();
}

// Always available (core Download Manager)
(new DownloadTracker())->register();
(new UserTracker())->register();
```

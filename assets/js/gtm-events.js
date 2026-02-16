/**
 * WPDM Google Tags - Client-side event helpers
 *
 * Handles:
 * 1. Cookie queue reader (for events pushed during downloads/redirects)
 * 2. Payment method tracking (add_payment_info)
 * 3. AJAX cart event interception (remove_from_cart via REST)
 * 4. Debug mode console logging
 *
 * @since 2.0.0
 */
(function($) {
    'use strict';

    var COOKIE_KEY = 'wpdm_gtag_queue';
    var config = window.wpdmGtagConfig || {};
    var debug = config.debug || false;

    /**
     * Initialize on DOM ready.
     */
    function init() {
        processCookieQueue();
        bindPaymentMethodTracking();
        bindAjaxCartTracking();

        if (debug) {
            enableDebugMode();
        }
    }

    /**
     * Read and process events from the cookie queue.
     * Events are pushed by PHP during non-page contexts (downloads, registrations).
     */
    function processCookieQueue() {
        var cookie = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_KEY + '=([^;]*)'));
        if (!cookie || !cookie[1]) return;

        try {
            var events = JSON.parse(decodeURIComponent(cookie[1]));
            if (!Array.isArray(events)) return;

            window.dataLayer = window.dataLayer || [];

            events.forEach(function(event) {
                if (event.ecommerce) {
                    dataLayer.push({ ecommerce: null });
                }
                dataLayer.push(event);

                if (debug) {
                    console.log('[WPDM GTags] Cookie event:', event);
                }
            });
        } catch(e) {
            if (debug) {
                console.error('[WPDM GTags] Cookie parse error:', e);
            }
        }

        // Clear the cookie
        document.cookie = COOKIE_KEY + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    }

    /**
     * Track payment method selection (add_payment_info).
     */
    function bindPaymentMethodTracking() {
        $(document).on('change', 'input[name="payment_method"], select[name="payment_method"]', function() {
            var checkoutData = window.wpdmGtagCheckout;
            if (!checkoutData) return;

            window.dataLayer = window.dataLayer || [];
            dataLayer.push({ ecommerce: null });
            dataLayer.push({
                event: 'add_payment_info',
                ecommerce: {
                    currency: checkoutData.currency,
                    value: parseFloat(checkoutData.cartTotal) || 0,
                    payment_type: $(this).val(),
                    items: checkoutData.cartItems || []
                }
            });

            if (debug) {
                console.log('[WPDM GTags] add_payment_info:', $(this).val());
            }
        });
    }

    /**
     * Intercept AJAX cart operations for dataLayer events.
     * Handles remove_from_cart via the REST API.
     */
    function bindAjaxCartTracking() {
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (!settings.url || !window.dataLayer) return;

            var url = settings.url;
            var method = (settings.type || 'GET').toUpperCase();

            // Cart item removed (DELETE /wpdmpp/v1/cart/{id})
            if (url.match(/wpdmpp\/v1\/cart\/\d+/) && method === 'DELETE') {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        var productId = url.match(/\/cart\/(\d+)/);
                        if (productId && productId[1]) {
                            dataLayer.push({ ecommerce: null });
                            dataLayer.push({
                                event: 'remove_from_cart',
                                ecommerce: {
                                    currency: config.currency || 'USD',
                                    items: [{ item_id: productId[1] }]
                                }
                            });

                            if (debug) {
                                console.log('[WPDM GTags] remove_from_cart (AJAX):', productId[1]);
                            }
                        }
                    }
                } catch(e) {}
            }
        });
    }

    /**
     * Enable debug mode — log all dataLayer pushes to console.
     */
    function enableDebugMode() {
        window.dataLayer = window.dataLayer || [];

        var originalPush = window.dataLayer.push;
        window.dataLayer.push = function() {
            for (var i = 0; i < arguments.length; i++) {
                var item = arguments[i];
                if (item && item.event) {
                    console.log('%c[WPDM GTags]%c ' + item.event, 'color: #6366f1; font-weight: bold', 'color: inherit', item);
                }
            }
            return originalPush.apply(this, arguments);
        };

        console.log('%c[WPDM GTags]%c Debug mode enabled', 'color: #6366f1; font-weight: bold', 'color: #10b981');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(jQuery);

<?php
/**
 * MeasurementProtocol - GA4 server-side event delivery
 *
 * Sends events directly to GA4 via the Measurement Protocol API.
 * Used for events that fire during non-browser requests (downloads, webhooks, cron).
 *
 * @package WPDMGoogleTags
 * @since 2.0.0
 */

namespace WPDMGoogleTags;

defined('ABSPATH') || exit;

class MeasurementProtocol {

    private static string $endpoint = 'https://www.google-analytics.com/mp/collect';

    /**
     * Send an event to GA4 via Measurement Protocol.
     *
     * @param array       $event    Event data with 'name' and 'params' keys
     * @param string|null $clientId Override client ID (defaults to WPDM device ID)
     * @return bool True if sent successfully
     */
    public static function send(array $event, ?string $clientId = null): bool {
        $measurementId = Settings::get('ga4_id', '');
        $apiSecret = Settings::get('mp_secret', '');

        if (!$measurementId || !$apiSecret) return false;

        // Use WPDM device ID as client_id for session continuity
        if (!$clientId) {
            $clientId = class_exists('\WPDM\__\Session')
                ? \WPDM\__\Session::deviceID()
                : self::generateClientId();
        }

        $payload = [
            'client_id' => $clientId,
            'events'    => [$event],
        ];

        $url = sprintf(
            '%s?measurement_id=%s&api_secret=%s',
            self::$endpoint,
            urlencode($measurementId),
            urlencode($apiSecret)
        );

        $response = wp_remote_post($url, [
            'body'     => wp_json_encode($payload),
            'headers'  => ['Content-Type' => 'application/json'],
            'timeout'  => 5,
            'blocking' => false, // Non-blocking — don't wait for response
        ]);

        if (!empty(Settings::get('debug_mode'))) {
            if (is_wp_error($response)) {
                error_log('[WPDM GTags MP] Error: ' . $response->get_error_message());
            } else {
                error_log('[WPDM GTags MP] Sent: ' . wp_json_encode($event));
            }
        }

        return !is_wp_error($response);
    }

    /**
     * Generate a random client ID if WPDM Session is unavailable.
     */
    private static function generateClientId(): string {
        return wp_generate_uuid4();
    }
}

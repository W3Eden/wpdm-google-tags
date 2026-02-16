<?php
/**
 * UserTracker - Tracks user sign-up and login events
 *
 * Pushes GA4 sign_up and login events via cookie queue
 * since both actions are followed by a redirect.
 *
 * @package WPDMGoogleTags\Tracker
 * @since 2.0.0
 */

namespace WPDMGoogleTags\Tracker;

use WPDMGoogleTags\DataLayer;
use WPDMGoogleTags\Settings;

defined('ABSPATH') || exit;

class UserTracker {

    public function register(): void {
        if (Settings::isEnabled('track_signup')) {
            add_action('user_register', [$this, 'onSignup']);
        }

        if (Settings::isEnabled('track_login')) {
            add_action('wp_login', [$this, 'onLogin'], 10, 2);
        }
    }

    /**
     * GA4 sign_up event.
     *
     * Hook: user_register
     * Param: $userId (int)
     */
    public function onSignup(int $userId): void {
        DataLayer::pushViaCookie([
            'event'  => 'sign_up',
            'method' => 'email',
        ]);
    }

    /**
     * GA4 login event.
     *
     * Hook: wp_login
     * Params: $userName (string), $user (WP_User)
     */
    public function onLogin(string $userName, \WP_User $user): void {
        DataLayer::pushViaCookie([
            'event'  => 'login',
            'method' => 'email',
        ]);
    }
}

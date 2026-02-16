<?php
/**
 * Settings Template for WPDM Google Tags
 *
 * @var array $settings Settings array from Settings::getAll()
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;
?>
<input type="hidden" name="save_gtm_settings" value="1">

<div class="panel panel-default">
    <div class="panel-heading">Google Tag Manager</div>
    <div class="panel-body">
        <div class="form-group">
            <label for="gtm-id">GTM Container ID</label>
            <input class="form-control" type="text" name="__wpdm_gtag[gtm_id]" id="gtm-id"
                   value="<?php echo esc_attr($settings['gtm_id']); ?>" placeholder="GTM-XXXXXXX">
            <em class="text-muted">Your Google Tag Manager container ID</em>
        </div>

        <div class="form-group">
            <label for="gtm-auth">Environment <code>gtm_auth</code> parameter <small class="text-muted">(optional)</small></label>
            <input class="form-control" type="text" name="__wpdm_gtag[gtm_auth]" id="gtm-auth"
                   value="<?php echo esc_attr($settings['gtm_auth']); ?>">
        </div>

        <div class="form-group">
            <label for="gtm-preview">Environment <code>gtm_preview</code> parameter <small class="text-muted">(optional)</small></label>
            <input class="form-control" type="text" name="__wpdm_gtag[gtm_preview]" id="gtm-preview"
                   value="<?php echo esc_attr($settings['gtm_preview']); ?>">
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">GA4 Measurement Protocol <small class="text-muted">(optional)</small></div>
    <div class="panel-body">
        <div class="form-group">
            <label for="ga4-id">GA4 Measurement ID</label>
            <input class="form-control" type="text" name="__wpdm_gtag[ga4_id]" id="ga4-id"
                   value="<?php echo esc_attr($settings['ga4_id']); ?>" placeholder="G-XXXXXXXXXX">
        </div>
        <div class="form-group">
            <label for="mp-secret">API Secret</label>
            <input class="form-control" type="password" name="__wpdm_gtag[mp_secret]" id="mp-secret"
                   value="<?php echo esc_attr($settings['mp_secret']); ?>">
        </div>
        <p class="text-muted" style="margin-top: -5px;">
            Required for reliable server-side download tracking. Get from GA4 Admin &rarr; Data Streams &rarr; Measurement Protocol API Secrets.
        </p>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">E-commerce Tracking</div>
    <div class="panel-body">

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_view_item]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_view_item]" value="1" <?php checked(1, $settings['track_view_item']); ?>>
                Track Product Views <code>view_item</code>
            </label>
            <br><em class="text-muted">Fires when a single package page is viewed</em>
        </div>

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_cart]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_cart]" value="1" <?php checked(1, $settings['track_cart']); ?>>
                Track Cart Events <code>add_to_cart</code> <code>remove_from_cart</code> <code>view_cart</code>
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_checkout]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_checkout]" value="1" <?php checked(1, $settings['track_checkout']); ?>>
                Track Checkout <code>begin_checkout</code> <code>add_payment_info</code>
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_purchase]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_purchase]" value="1" <?php checked(1, $settings['track_purchase']); ?>>
                Track Purchases <code>purchase</code>
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_refund]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_refund]" value="1" <?php checked(1, $settings['track_refund']); ?>>
                Track Refunds <code>refund</code>
            </label>
        </div>

    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">Other Events</div>
    <div class="panel-body">

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_download]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_download]" value="1" <?php checked(1, $settings['track_download']); ?>>
                Track Downloads <code>file_download</code>
            </label>
            <br><em class="text-muted">Server-side tracking when a file download starts</em>
        </div>

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_signup]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_signup]" value="1" <?php checked(1, $settings['track_signup']); ?>>
                Track User Signups <code>sign_up</code>
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[track_login]" value="0">
                <input type="checkbox" name="__wpdm_gtag[track_login]" value="1" <?php checked(1, $settings['track_login']); ?>>
                Track User Logins <code>login</code>
            </label>
        </div>

    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">Debug</div>
    <div class="panel-body">
        <div class="form-group">
            <label>
                <input type="hidden" name="__wpdm_gtag[debug_mode]" value="0">
                <input type="checkbox" name="__wpdm_gtag[debug_mode]" value="1" <?php checked(1, $settings['debug_mode']); ?>>
                Enable Debug Mode
            </label>
            <br><em class="text-muted">Log all events to browser console and PHP error log</em>
        </div>
    </div>
</div>

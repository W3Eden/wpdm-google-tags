<?php
/**
 * GTMEmbed - Google Tag Manager container injection
 *
 * Handles the GTM container script in <head> and <noscript> iframe after <body>.
 * All output is properly escaped to prevent XSS.
 *
 * @package WPDMGoogleTags
 * @since 2.0.0
 */

namespace WPDMGoogleTags;

defined('ABSPATH') || exit;

class GTMEmbed {

    public function register(): void {
        add_action('wp_head', [$this, 'headScript'], 1);
        add_action('wp_body_open', [$this, 'bodyNoscript'], 1);
        add_action('wp_footer', [$this, 'bodyNoscriptFallback'], 1);
        add_action('wp_footer', [DataLayer::class, 'render'], 999);
    }

    /**
     * Output GTM container script in <head>.
     */
    public function headScript(): void {
        $gtmId = $this->getValidGtmId();
        if (!$gtmId) return;

        $auth = Settings::get('gtm_auth', '');
        $preview = Settings::get('gtm_preview', '');

        $envParams = '';
        if ($auth && $preview) {
            $envParams = "&gtm_auth=" . esc_js($auth) . "&gtm_preview=" . esc_js($preview) . "&gtm_cookies_win=x";
        }

        $gtmIdJs = esc_js($gtmId);

        echo "\n<!-- Google Tag Manager -->\n";
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
        echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
        echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
        echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl+'" . $envParams . "';f.parentNode.insertBefore(j,f);\n";
        echo "})(window,document,'script','dataLayer','" . $gtmIdJs . "');</script>\n";
        echo "<!-- End Google Tag Manager -->\n";
    }

    /**
     * Output GTM noscript iframe after <body> via wp_body_open.
     */
    public function bodyNoscript(): void {
        $gtmId = $this->getValidGtmId();
        if (!$gtmId) return;

        $gtmIdAttr = esc_attr($gtmId);

        echo "\n<!-- Google Tag Manager (noscript) -->\n";
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtmIdAttr . '"';
        echo ' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
        echo "\n<!-- End Google Tag Manager (noscript) -->\n";
    }

    /**
     * Fallback for themes that don't support wp_body_open.
     */
    public function bodyNoscriptFallback(): void {
        if (!did_action('wp_body_open')) {
            $this->bodyNoscript();
        }
    }

    /**
     * Get and validate the GTM container ID.
     *
     * @return string Valid GTM ID or empty string
     */
    private function getValidGtmId(): string {
        $id = Settings::get('gtm_id', '');
        if (!$id) return '';

        // Validate format: GTM-XXXXXXX (letters and numbers)
        if (!preg_match('/^GTM-[A-Z0-9]+$/i', $id)) return '';

        return $id;
    }
}

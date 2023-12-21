<?php
/**
 * Plugin Name:  WPDM - Google Tags
 * Plugin URI: https://www.wpdownloadmanager.com/
 * Description: Google Tags for WPDM
 * Author: WordPress Download Manager
 * Version: 1.0.0
 * Author URI: https://www.wpdownloadmanager.com/
 * Update URI: wpdm-google-tags
 */

namespace WPDM\AddOn;

use WPDM\Admin\Menu\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleTags {
	private static $instance;
	private $dir, $url;

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self;
			self::$instance->actions();
			self::$instance->dir = dirname( __FILE__ );
			self::$instance->url = WP_PLUGIN_URL . '/' . basename( self::$instance->dir );
		}

		return self::$instance;
	}

	private function actions() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );


		if ( is_admin() ) {
			add_filter( 'add_wpdm_settings_tab', array( $this, 'settings_tab' ) );
		}

		add_action( 'wpdm_before_email_download_link', array( $this, 'pushTogtag' ), 10, 2 );

		add_action( "admin_head", [ $this, 'admin_head' ] );

        add_action("wp_head", [ $this, 'embedCode']);

        add_action("user_register", [ $this, 'tagUserSignup']);

        add_action("wp_footer", [ $this, 'footerCode']);

	}

	function admin_head() {
		?>

		<?php
	}

	function tagUserSignup() {
		setcookie(
			'wpdm_gtag_user_registered',
			'1',
			0,
			'/',
			'',
			( false !== strstr( get_option( 'home' ), 'https:' ) ) && is_ssl(),
			true
		);
	}

	function settings_tab( $tabs ) {
		$tabs['wpdm-google-tags'] = Settings::createMenu( 'wpdm-google-tags', 'Google Tags', array(
			$this,
			'settings'
		), 'fas fa-tags' );

		return $tabs;

	}

	public function activate() {

	}

	public function deactivate() {

	}

	public static function getDir() {
		return self::$instance->dir;
	}

	public static function getUrl() {
		return self::$instance->url;
	}

	function embedCode() {
        $gtm_id = get_option('__wpdm_gtag_id');
        $gtm_auth = get_option('__wpdm_gtm_auth');
        $gtm_preview = get_option('__wpdm_gtm_preview');
		if($gtm_auth && $gtm_preview) {
        ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl+ '&gtm_auth=<?= $gtm_auth ?>&gtm_preview=<?= $gtm_preview ?>&gtm_cookies_win=x';f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?= $gtm_id ?>');</script>
        <!-- End Google Tag Manager -->
        <?php } else { ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?= $gtm_id ?>');</script>
        <!-- End Google Tag Manager -->

        <?php
		}
    }

    function footerCode() {
	    $gtm_id = get_option('__wpdm_gtag_id');
	    $gtm_usn = get_option('__wpdm_gtag_signup');
	    $gtm_uln = get_option('__wpdm_gtag_login');
	    $gtm_dle = get_option('__wpdm_gtag_dle');
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $gtm_id ?>"
                          height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->

        <script>
		    <?php if($gtm_usn) { ?>
            WPDM.addAction("wpdm_new_signup", function (data) {
                if ( window.dataLayer) {
                    window.dataLayer.push({
                        'event': 'WPDM.newSignup',
                        'pagePath': location.href,
                        'pageTitle': document.title,
                        'visitorType': 'visitor'

                    });
                    console.log('pushed WPDM.newSignup!')
                } else {
                    console.log('window.dataLayer not found!')
                }
            });
		    <?php } ?>

            <?php if($gtm_uln) { ?>
            WPDM.addAction("wpdm_user_login", function (data) {
                if ( window.dataLayer) {
                    window.dataLayer.push({
                        'event': 'WPDM.userLogin',
                        'pagePath': location.href,
                        'pageTitle': document.title,
                        'visitorType': 'visitor'

                    });
                    console.log('pushed WPDM.userLogin!')
                } else {
                    console.log('window.dataLayer not found!')
                }
            });
		    <?php } ?>

		    <?php if($gtm_dle) { ?>
            jQuery('body').on('click', '.wpdm-download-button, .wpdm-download-link, .inddl', function () {
                if ( window.dataLayer) {
                    window.dataLayer.push({
                        'event': 'WPDM.newDownload',
                        'pagePath': location.href,
                        'pageTitle': document.title ,
                        'visitorType': 'visitor'
                    });
                    console.log('pushed WPDM.newDownload!')
                }
                else {
                    console.log('window.dataLayer not found!')
                }
            });
		    <?php } ?>
        </script>
        <?php
    }


	public function settings() {

		if ( wpdm_query_var('save_gtm_settings', 'int') === 1) {
			//update_option('__wpdm_gtag_apikey_name',wpdm_query_var('__wpdm_gtag_apikey_name'));
			update_option( '__wpdm_gtag_id', wpdm_query_var( '__wpdm_gtag_id' ) );
			update_option( '__wpdm_gtag_signup', (int)wpdm_query_var( '__wpdm_gtag_signup' ) );
			update_option( '__wpdm_gtag_login', (int)wpdm_query_var( '__wpdm_gtag_login' ) );
			update_option( '__wpdm_gtag_dle', (int)wpdm_query_var( '__wpdm_gtag_dle' ) );
			update_option( '__wpdm_gtm_auth', wpdm_query_var( '__wpdm_gtm_auth' ) );
			update_option( '__wpdm_gtm_preview', wpdm_query_var( '__wpdm_gtm_preview' ) );
			update_option( '__wpdm_gtag_purchase', wpdm_query_var( '__wpdm_gtag_purchase' ) );
			update_option( '__wpdm_gtag_renew', wpdm_query_var( '__wpdm_gtag_renew' ) );
			die( 'Settings Saved Successfully.' );
		}


        $gtagid = get_option('__wpdm_gtag_id');

		include __DIR__.'/tpls/settings.php';
	}


	public function pushTogtag( $post, $file ) {

		$keyname = 'api-key';// get_option('__wpdm_gtag_apikey_name');
		$key     = get_option( '__wpdm_gtag_apikey' );

		if ( ! $key ) {
			return;
		}

		$name       = $post['name'];
		$names      = explode( ' ', $name );
		$first_name = $names[0];
		$last_name  = wpdm_valueof( $names, 1, $names[0] );

		$email = $post['email'];

		if ( is_email( $email ) ) {

			$credentials = Configuration::getDefaultConfiguration()->setApiKey( $keyname, $key );

			$apiInstance = new ContactsApi(
				new Client(),
				$credentials
			);

			$createContact = new \gtag\Client\Model\CreateContact( [
				'email'         => $email,
				'updateEnabled' => true,
				'attributes'    => [ 'FIRSTNAME' => $first_name, 'LASTNAME' => $last_name ],
				'listIds'       => [ (int) get_option( '__wpdm_gtag_list' ) ]
			] );

			try {
				$result = $apiInstance->createContact( $createContact );
			} catch ( \Exception $e ) {
				print_r( $e );
				echo 'Exception when calling ContactsApi->createContact: ', $e->getMessage(), PHP_EOL;
			}


		}
	}

}

if ( defined( 'WPDM_VERSION' ) ) {
	GoogleTags::getInstance();

	add_filter( 'update_plugins_wpdm-google-tags', function ( $update, $plugin_data, $plugin_file, $locales ) {
		$id                = basename( __DIR__ );
		$latest_versions   = WPDM()->updater->getLatestVersions();
		$latest_version    = wpdm_valueof( $latest_versions, $id );
		$access_token      = wpdm_access_token();
		$update            = [];
		$update['id']      = $id;
		$update['slug']    = $id;
		$update['url']     = $plugin_data['PluginURI'];
		$update['tested']  = true;
		$update['version'] = $latest_version;
		$update['package'] = $access_token !== '' ? "https://www.wpdownloadmanager.com/?wpdmpp_file={$id}.zip&access_token={$access_token}" : '';

		return $update;
	}, 10, 4 );

}
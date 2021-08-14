<?php
/**
 * Plugin Name: MarchGuard WooCommerce Integration
 * Plugin URI: https://imaginate-solutions.com/
 * Description: Plugin to integrate TH March for insuring products.
 * Version: 1.0.0
 * Author: Imaginate Solutions
 * Author URI: https://imaginate-solutions.com/
 * Text Domain: thmarch-integration
 * Domain Path: /i18n/languages/
 * Requires at least: 5.5
 * Requires PHP: 7.0
 *
 * @package THMarch
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TH_March_Integration' ) ) {

	/**
	 * Main class to initiate the plugin.
	 */
	class TH_March_Integration {

		/**
		 * TH March Integration.
		 *
		 * @var string $plugin_name The string used to uniquely identify this plugin.
		 */
		protected $plugin_name = 'TH March Integration for WooCommerce';

		/**
		 * Version 1.0.0
		 *
		 * @var string $version The current version of the plugin.
		 */
		protected $version = '1.0.0';

		/**
		 * Constructor.
		 */
		public function __construct() {

			$this->define_constants();

			if ( is_admin() ) {
				$this->load_admin();
			}

			$this->load_frontend();

		}

		/**
		 * Define constants to be used in the plugin.
		 *
		 * @return void
		 */
		public function define_constants() {
			$this->define( 'TH_PLUGIN_SLUG', 'thm-integration' );
			$this->define( 'TH_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
			$this->define( 'TH_PLUGIN_VERSION', '1.0.0' );
			$this->define( 'TH_MARCHGUARD_URL', 'https://stage.marchguard.com/' );

			$upload = wp_get_upload_dir();

			$upload_dir = $upload['basedir'] . '/thmcertificates';
			$this->define( 'TH_MARCHGUARD_UPLOAD_DIR', $upload_dir );

			$upload_url = $upload['baseurl'] . '/thmcertificates';
			$this->define( 'TH_MARCHGUARD_UPLOAD_URL', $upload_url );
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string      $name  Constant Name.
		 * @param string|bool $value Constant Value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Set Locale
		 */
		private function set_locale() {
			load_plugin_textdomain( 'thm-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages/' );
		}

		/**
		 * Load frontend.
		 *
		 * @return void
		 */
		public function load_frontend() {
			require_once 'includes/class-thmarch-frontend.php';
			require_once 'includes/class-thmarch-orders.php';
		}

		/**
		 * Load Admin.
		 *
		 * @return void
		 */
		public function load_admin() {
			require_once 'includes/class-thmarch-admin.php';
		}
	}
}

$th_march_loader = new TH_March_Integration();

/**
 * Activate the plugin.
 */
function thm_integration_activate() {
	$upload     = wp_get_upload_dir();
	$upload_dir = $upload['basedir'];
	$upload_dir = $upload_dir . '/thmcertificates';
	if ( ! is_dir( $upload_dir ) ) {
		wp_mkdir_p( $upload_dir );
	}
}
register_activation_hook( __FILE__, 'thm_integration_activate' );

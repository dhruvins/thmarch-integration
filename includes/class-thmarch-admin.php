<?php
/**
 * Admin class file for settings.
 *
 * @package THMarch
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'THMarch_Admin' ) ) {

	/**
	 * THMarch Admin Class.
	 */
	class THMarch_Admin {

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'woocommerce_settings_tabs_array', array( &$this, 'thm_woocommerce_settings_tabs_option' ), 999 );
			add_action( 'woocommerce_settings_tabs_' . TH_PLUGIN_SLUG, array( &$this, 'thm_program_settings_tab' ) );
			add_action( 'woocommerce_settings_save_' . TH_PLUGIN_SLUG, array( &$this, 'thm_program_settings_save' ) );
		}

		/**
		 * Settings tab.
		 *
		 * @param array $settings_tabs Settings Tab.
		 * @return array
		 */
		public function thm_woocommerce_settings_tabs_option( $settings_tabs ) {
			$settings_tabs['thm-integration'] = esc_html__( 'MarchGuard Settings', 'thm-integration' );
			return $settings_tabs;
		}

		/**
		 * This function will display the settings.
		 */
		public function thm_program_settings_tab() {
			woocommerce_admin_fields( $this->thm_get_seetings() );

			echo '
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label>Shortcode</label>
						</th>
						<td class="forminp forminp-text">
							<p>[thm_insurance_details] - Use the following shortcode to display the insurance checkbox anywhere on the Product Page.</p>
						</td>
					</tr>
				</tbody>
			</table>';
		}

		/**
		 * Display the html of each sections using Setting API.
		 */
		public function thm_get_seetings() {
			$settings = array(
				array(
					'title' => esc_html__( 'MarchGuard Settings', 'thm-integration' ),
					'type'  => 'title',
					'id'    => 'wc_thm_settings',
				),
				array(
					'title'    => esc_html__( 'Enable Insurance on the Cart Page', 'thm-integration' ),
					'type'     => 'checkbox',
					'id'       => 'wc_thm_enable',
					'class'    => 'wc_thm_enable',
					'desc_tip' => __( 'Select this option to enable users to select insurance on the cart page', 'thm-integration' ),
				),
				array(
					'title'    => esc_html__( 'Store ID', 'thm-integration' ),
					'type'     => 'text',
					'id'       => 'wc_store_id',
					'class'    => 'wc_store_id',
					'desc_tip' => __( 'Set the Store ID here.', 'thm-integration' ),
				),
				array(
					'title'    => esc_html__( 'Store Pin', 'thm-integration' ),
					'type'     => 'text',
					'id'       => 'wc_store_pin',
					'class'    => 'wc_store_pin',
					'desc_tip' => __( 'Set the Store Pin here.', 'thm-integration' ),
				),
				array(
					'title'    => esc_html__( 'Store Name', 'thm-integration' ),
					'type'     => 'text',
					'id'       => 'wc_store_name',
					'class'    => 'wc_store_name',
					'desc_tip' => __( 'Set the Store Name here. This name will appear in the terms and conditions.', 'thm-integration' ),
				),
				array(
					'title'   => __( 'Select Categories', 'thm-integration' ),
					'id'      => 'wc_thm_categories',
					'desc'    => __( 'Select the categories for insurance to be available for', 'thm-integration' ),
					'default' => '',
					'type'    => 'multiselect',
					'class'   => 'chosen_select',
					'css'     => 'width: 100%;',
					'options' => $this->get_terms( 'product_cat' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'wc_thm_settings',
				),
			);
			$settings = apply_filters( 'wc_thm_settings', $settings );
			return $settings;
		}

		/**
		 * Get_terms.
		 *
		 * @param array $args Array of arguments.
		 * @return array
		 */
		public function get_terms( $args ) {
			if ( ! is_array( $args ) ) {
				$_taxonomy = $args;
				$args      = array(
					'taxonomy'   => $_taxonomy,
					'orderby'    => 'name',
					'hide_empty' => false,
				);
			}
			global $wp_version;
			if ( version_compare( $wp_version, '4.5.0', '>=' ) ) {
				$_terms = get_terms( $args );
			} else {
				$_taxonomy = $args['taxonomy'];
				unset( $args['taxonomy'] );
				$_terms = get_terms( $_taxonomy, $args );
			}
			$_terms_options = array();
			if ( ! empty( $_terms ) && ! is_wp_error( $_terms ) ) {
				foreach ( $_terms as $_term ) {
					$_terms_options[ $_term->term_id ] = $_term->name;
				}
			}
			return $_terms_options;
		}

		/**
		 * Save the data using Setting API
		 */
		public function thm_program_settings_save() {
			global $current_section;
			WC_Admin_Settings::save_fields( $this->thm_get_seetings() );
		}
	}

}

return new THMarch_Admin();

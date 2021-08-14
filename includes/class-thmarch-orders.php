<?php
/**
 * Orders class file.
 *
 * @package THMarch
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'THMarch_Orders' ) ) {

	/**
	 * Orders Class.
	 */
	class THMarch_Orders {

		/**
		 * Constructor function.
		 */
		public function __construct() {
			add_action( 'woocommerce_checkout_order_processed', array( &$this, 'thm_send_final_quotation' ), 10, 3 );

			//add_action( 'woocommerce_order_status_changed', array( &$this, 'thm_generate_certificate' ), 10, 3 );

			add_action( 'woocommerce_order_details_after_order_table', array( &$this, 'thm_add_certificate_link' ), 10, 1 );

			add_filter( 'woocommerce_order_actions', array( &$this, 'thm_add_certificate_action' ), 10, 1 );

			add_action( 'woocommerce_order_action_generate_certificate', array( &$this, 'thm_activate_certificate' ), 10, 1 );
		}

		/**
		 * Add certificate generation action.
		 *
		 * @param array $actions Actions.
		 * @return array
		 */
		public function thm_add_certificate_action( $actions ) {
			global $theorder;

			// This is used by some callbacks attached to hooks such as woocommerce_order_actions which rely on the global to determine if actions should be displayed for certain orders.
			if ( ! is_object( $theorder ) ) {
				$theorder = wc_get_order( $post->ID );
			}

			$order_id = $theorder->get_id();

			$quotation_reference = get_post_meta( $order_id, 'thm_quotation_reference', true );

			$acceptance_reference = get_post_meta( $order_id, 'thm_acceptance_reference', true );

			if ( $quotation_reference && $acceptance_reference ) {
				$actions['generate_certificate'] = __( 'Generate Certificate & Activate Insurance', 'thmarch-integration' );
			}
			return $actions;
		}

		/**
		 * Accept insurance.
		 *
		 * @param string   $action Action.
		 * @param WC_Order $order  Order.
		 * @return void
		 */
		public function thm_activate_certificate( $order ) {
			$order_id  = $order->get_id();
			$store_id  = get_option( 'wc_store_id', '' );
			$store_pin = get_option( 'wc_store_pin', '' );
			$quote_ref = get_post_meta( $order_id, 'thm_acceptance_reference', true );

			if ( 'yes' === get_option( 'wc_thm_enable', '' ) && '' !== $store_id && '' !== $store_pin && '' !== $quote_ref ) {

				$request_xml = '
					<certificate_request>
						<store_id>' . $store_id . '</store_id>
						<store_pin>' . $store_pin . '</store_pin>
						<reference>' . $quote_ref . '</reference>
						<reference_only>false</reference_only>
						<certificate_only>true</certificate_only>
					</certificate_request>';

				// The request URL.
				$url = TH_MARCHGUARD_URL . 'public_services/certificate_request';

				// The fields to POST.
				$fields = 'xml=' . rawurlencode( $request_xml );

				// Setup curl.
				$curl_handler = curl_init( $url );
				curl_setopt( $curl_handler, CURLOPT_SSL_VERIFYPEER, false ); 
				curl_setopt( $curl_handler, CURLOPT_HEADER, false );
				curl_setopt( $curl_handler, CURLOPT_POST, true );
				curl_setopt( $curl_handler, CURLOPT_POSTFIELDS, $fields );
				curl_setopt( $curl_handler, CURLOPT_RETURNTRANSFER, true );
				$result = curl_exec( $curl_handler );

				file_put_contents( TH_MARCHGUARD_UPLOAD_DIR . '/' . $quote_ref . '.pdf', $result );

				header( 'Content-type: application/pdf' );
				header( 'Content-Disposition: attachment; filename="' . $quote_ref . '.pdf"' );

				add_post_meta( $order_id, 'thm_certificate', TH_MARCHGUARD_UPLOAD_URL . '/' . $quote_ref . '.pdf' );
			}
		}

		/**
		 * Send Final Quotation.
		 *
		 * @param int      $order_id Order ID.
		 * @param array    $posted_data POST data.
		 * @param WC_Order $order Order.
		 * @return void
		 */
		public function thm_send_final_quotation( $order_id, $posted_data, $order ) {

			$store_id  = get_option( 'wc_store_id', '' );
			$store_pin = get_option( 'wc_store_pin', '' );

			$allowed_cats = get_option( 'wc_thm_categories', array() );

			if ( 0 === count( $allowed_cats ) ) {
				return;
			}

			if ( 'yes' === get_option( 'wc_thm_enable', '' ) && '' !== $store_id && '' !== $store_pin ) {

				$customer_postcode = $posted_data['billing_postcode'];

				$insurance_data = WC()->session->get( 'thm_insurance' );
				if ( '' === $insurance_data || null === $insurance_data ) {
					return;
				}
				foreach ( $insurance_data as $c_key => $c_value ) {
					$terms = isset( $c_value['term'] ) ? $c_value['term'] : 1;
				}

				$request_xml = '
					<quotation_request>
						<store_id>' . $store_id . '</store_id>
						<store_pin>' . $store_pin . '</store_pin>
						<term>' . $terms . '</term>
						<rating_postcode><![CDATA[' . $customer_postcode . ']]></rating_postcode>
						<items>';

				$order_contents = $order->get_items();

				$items = '';

				foreach ( $order_contents as $item_key => $item_value ) {
					$price_item_meta = wc_get_order_item_meta( $item_key, 'Product Price', true );

					$product_id   = $item_value->get_product_id();
					$variation_id = $item_value->get_variation_id();
					$product_name = $item_value->get_name();
					$product      = wc_get_product( $product_id );
					$product_var  = wc_get_product( $variation_id );
					$price        = ( '' !== $variation_id && 0 !== $variation_id ) ? $product_var->get_regular_price() : $product->get_regular_price();
					$cost         = ( isset( $price_item_meta ) && '' !== $price_item_meta ) ? $price_item_meta : $price;
					$categories   = $product->get_category_ids();

					$allowed = ! empty( array_intersect( $categories, $allowed_cats ) );

					if ( ! $allowed ) {
						continue;
					}

					$api_category = 'Jewellery';
					foreach ( $categories as $term_id ) {
						$term_category = get_term_by( 'id', $term_id, 'product_cat' );

						if ( false !== stripos( $term_category->name, 'watch' ) ) {
							$api_category = 'Watch';
							break;
						}
					}

					if ( 'Watch' === $api_category && 1501 < $cost ) {
						continue;
					} elseif ( 'Jewellery' === $api_category && 5001 < $cost ) {
						continue;
					}

					$items .= '<item>
						<id>' . $product_id . '</id>
						<description><![CDATA[' . $product_name . ']]></description>
						<cost>' . $cost . '</cost>
						<value>' . $cost . '</value>
						<category>' . $api_category . '</category>
						<include>true</include>
					</item>';
				}

				$client = '<client>
					<title><![CDATA[]]></title>
					<first_name><![CDATA[' . $posted_data['billing_first_name'] . ']]></first_name>
					<last_name><![CDATA[' . $posted_data['billing_last_name'] . ']]></last_name>
					<address_1><![CDATA[' . $posted_data['billing_address_1'] . ']]></address_1>
					<address_2><![CDATA[' . $posted_data['billing_address_2'] . ']]></address_2>
					<address_3><![CDATA[]]></address_3>
					<town><![CDATA[' . $posted_data['billing_city'] . ']]></town>
					<county><![CDATA[' . $posted_data['billing_state'] . ']]></county>
					<postcode><![CDATA[' . $posted_data['billing_postcode'] . ']]></postcode>
					<email_address><![CDATA[' . $posted_data['billing_email'] . ']]></email_address>
					<telephone_number><![CDATA[' . $posted_data['billing_phone'] . ']]></telephone_number>
				</client>';

				$request_xml = $request_xml . $items . '
					</items>
					' . $client . '
				</quotation_request>';

				// The request URL.
				$url = TH_MARCHGUARD_URL . 'public_services/quotation_request';

				// The fields to POST.
				$fields = 'xml=' . rawurlencode( $request_xml );

				// Setup curl.
				$curl_handler = curl_init( $url );
				curl_setopt( $curl_handler, CURLOPT_SSL_VERIFYPEER, false ); 
				curl_setopt( $curl_handler, CURLOPT_HEADER, false );
				curl_setopt( $curl_handler, CURLOPT_POST, true );
				curl_setopt( $curl_handler, CURLOPT_POSTFIELDS, $fields );
				curl_setopt( $curl_handler, CURLOPT_RETURNTRANSFER, true );
				$result = curl_exec( $curl_handler );

				$document = new DOMDocument();

				$document->loadXML( $result );
				$xpath = new DOMXPath( $document );

				$entries             = $xpath->query( '//quotation/reference' );
				$quotation_reference = '';
				if ( null !== $entries && $entries->length > 0 ) {
					$quotation_reference = $entries[0]->nodeValue;
				}

				add_post_meta( $order_id, 'thm_quotation_reference', $quotation_reference );

				$request_xml = '
					<quotation_accept_request>
						<store_id>' . $store_id . '</store_id>
						<store_pin>' . $store_pin . '</store_pin>
						<reference>' . $quotation_reference . '</reference>
					</quotation_accept_request>';

				// The request URL.
				$url = TH_MARCHGUARD_URL . 'public_services/quotation_accept_request';

				// The fields to POST.
				$fields = 'xml=' . rawurlencode( $request_xml );

				// Setup curl.
				$curl_handler = curl_init( $url );
				curl_setopt( $curl_handler, CURLOPT_SSL_VERIFYPEER, false ); 
				curl_setopt( $curl_handler, CURLOPT_HEADER, false );
				curl_setopt( $curl_handler, CURLOPT_POST, true );
				curl_setopt( $curl_handler, CURLOPT_POSTFIELDS, $fields );
				curl_setopt( $curl_handler, CURLOPT_RETURNTRANSFER, true );
				$result = curl_exec( $curl_handler );

				$document = new DOMDocument();
				$document->loadXML( $result );
				$xpath = new DOMXPath( $document );

				$entries              = $xpath->query( '//reference' );
				$acceptance_reference = '';
				if ( null !== $entries && $entries->length > 0 ) {
					$acceptance_reference = $entries[0]->nodeValue;
				}

				add_post_meta( $order_id, 'thm_acceptance_reference', $acceptance_reference );

				$order->add_order_note( 'MarchGuard Policy Reference Number: ' . $acceptance_reference . '.' );

				WC()->session->__unset( 'thm_insurance' );
			}
		}

		/**
		 * Generate Certificate once order payment is completed.
		 *
		 * @param int    $order_id      Order ID.
		 * @param string $wc_old_status Old Status.
		 * @param string $wc_new_status New Status.
		 * @return void
		 */
		public function thm_generate_certificate( $order_id, $wc_old_status, $wc_new_status ) {
			if ( 'pending' !== $wc_new_status && 'failed' !== $wc_new_status && 'cancelled' !== $wc_new_status && 'trash' !== $wc_new_status ) {

				$store_id  = get_option( 'wc_store_id', '' );
				$store_pin = get_option( 'wc_store_pin', '' );
				$quote_ref = get_post_meta( $order_id, 'thm_acceptance_reference', true );

				if ( 'yes' === get_option( 'wc_thm_enable', '' ) && '' !== $store_id && '' !== $store_pin && '' !== $quote_ref ) {

					$request_xml = '
						<certificate_request>
							<store_id>' . $store_id . '</store_id>
							<store_pin>' . $store_pin . '</store_pin>
							<reference>' . $quote_ref . '</reference>
							<reference_only>false</reference_only>
							<certificate_only>true</certificate_only>
						</certificate_request>';

					// The request URL.
					$url = TH_MARCHGUARD_URL . 'public_services/certificate_request';

					// The fields to POST.
					$fields = 'xml=' . rawurlencode( $request_xml );

					// Setup curl.
					$curl_handler = curl_init( $url );
					curl_setopt( $curl_handler, CURLOPT_SSL_VERIFYPEER, false ); 
					curl_setopt( $curl_handler, CURLOPT_HEADER, false );
					curl_setopt( $curl_handler, CURLOPT_POST, true );
					curl_setopt( $curl_handler, CURLOPT_POSTFIELDS, $fields );
					curl_setopt( $curl_handler, CURLOPT_RETURNTRANSFER, true );
					$result = curl_exec( $curl_handler );

					file_put_contents( TH_MARCHGUARD_UPLOAD_DIR . '/' . $quote_ref . '.pdf', $result );

					header( 'Content-type: application/pdf' );
					header( 'Content-Disposition: attachment; filename="' . $quote_ref . '.pdf"' );

					add_post_meta( $order_id, 'thm_certificate', TH_MARCHGUARD_UPLOAD_URL . '/' . $quote_ref . '.pdf' );

					WC()->session->__unset( 'thm_insurance' );
				}
			}
		}

		/**
		 * Add certificate link after order table.
		 *
		 * @param WC_Order $order Order.
		 * @return void
		 */
		public function thm_add_certificate_link( $order ) {
			$order_id = $order->get_id();

			$certificate = get_post_meta( $order_id, 'thm_certificate', true );

			if ( '' !== $certificate ) {
				echo '
				<div class="thm-certificate-div">
					<a href="' . esc_url( $certificate ) . '" target="_blank" class="thm-certificate-link">
						<img src="' . esc_url( TH_PLUGIN_URL ) . '/assets/img/certificate.png" style="width: 25px; display: inline-block;">
						<span>Download MarchGuard Insurance Certificate</span>
					</a>
				</div>';
			}
		}
	}
}

return new THMarch_Orders();

<?php
/**
 * Frontend class file.
 *
 * @package THMarch
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'THMarch_Frontend' ) ) {

	/**
	 * Frontend Class.
	 */
	class THMarch_Frontend {

		/**
		 * Constructor function.
		 */
		public function __construct() {
			add_action( 'woocommerce_after_cart_item_name', array( &$this, 'thm_add_insurance_selector' ), 10, 2 );

			// Modal for terms and final amount.
			add_action( 'woocommerce_before_cart', array( &$this, 'thm_add_terms_modal' ) );
			add_action( 'woocommerce_cart_contents', array( &$this, 'thm_hidden_variables' ) );

			add_action( 'wp_enqueue_scripts', array( &$this, 'thm_enqueue_styles' ) );

			add_action( 'wp_ajax_thm_update_fees', array( &$this, 'thm_update_fees' ) );
			add_action( 'wp_ajax_no_priv_thm_update_fees', array( &$this, 'thm_update_fees' ) );

			add_action( 'woocommerce_cart_calculate_fees', array( &$this, 'thm_update_insurance_fee' ), 10, 1 );

			add_shortcode( 'thm_insurance_details', array( &$this, 'thm_shortcode_callback' ) );

			//add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'thm_hidden_variables' ) );
			add_action( 'woocommerce_add_to_cart', array( &$this, 'thm_add_insurance_from_product' ), 10, 1 );

			add_action( 'woocommerce_cart_item_removed', array( &$this, 'thm_cart_item_removed' ), 10, 2 );
		}

		/**
		 * Add Insurance Selector checkbox after the cart content table.
		 *
		 * @param array  $cart_value    Cart Item.
		 * @param string $cart_item_key Item Key.
		 * @return void
		 */
		public function thm_add_insurance_selector( $cart_value, $cart_item_key ) {

			$store_id  = get_option( 'wc_store_id', '' );
			$store_pin = get_option( 'wc_store_pin', '' );

			$product_id = $cart_value['product_id'];
			$product    = wc_get_product( $product_id );

			$variation_id = isset( $cart_value['variation_id'] ) ? $cart_value['variation_id'] : '';
			if ( '' !== $variation_id ) {
				$product_variation = wc_get_product( $variation_id );
			}

			$categories   = $product->get_category_ids();
			$allowed_cats = get_option( 'wc_thm_categories', array() );

			if ( 0 === count( $allowed_cats ) ) {
				return;
			}

			$product_price = ( '' !== $variation_id && 0 !== $variation_id ) ? $product_variation->get_regular_price() : $product->get_regular_price();
			if ( isset( $cart_value['ag_product_details'] ) && isset( $cart_value['ag_product_details']['ag_price'] ) && '' !== $cart_value['ag_product_details']['ag_price'] ) {
				$product_price = $cart_value['ag_product_details']['ag_price'];
			}

			$allowed = ! empty( array_intersect( $categories, $allowed_cats ) );

			if ( 'yes' === get_option( 'wc_thm_enable', '' ) && '' !== $store_id && '' !== $store_pin && 5001 > $product_price && $allowed ) {

				$customer_postcode = WC()->customer->get_billing_postcode();

				if ( '' === $customer_postcode ) {
					$customer_postcode = get_option( 'woocommerce_store_postcode', 'CH41 2PX' );
				}

				$terms = ( null !== WC()->session->get( 'thm_insurance_term' ) ) ? WC()->session->get( 'thm_insurance_term' ) : '1';

				$selected_one   = '';
				$selected_three = '';

				$checked = '';
				if ( null !== WC()->session->get( 'thm_insurance' ) ) {
					$added_insurance = WC()->session->get( 'thm_insurance' );
					if ( isset( $added_insurance[ $cart_item_key ] ) ) {
						$checked = 'checked="checked"';

						$terms = $added_insurance[ $cart_item_key ]['term'];
						if ( '1' === $terms ) {
							$selected_one = 'selected="selected"';
						} elseif ( '3' === $terms ) {
							$selected_three = 'selected="selected"';
						}
					}
				}

				if ( isset( $_POST[ 'thm_insurance_term_' . $cart_item_key ] ) && '' !== $_POST[ 'thm_insurance_term_' . $cart_item_key ] ) {
					$terms = $_POST[ 'thm_insurance_term_' . $cart_item_key ];

					if ( '1' === $terms ) {
						$selected_one = 'selected="selected"';
					} elseif ( '3' === $terms ) {
						$selected_three = 'selected="selected"';
					}
				}

				$request_xml = '
					<quotation_request>
						<store_id>' . $store_id . '</store_id>
						<store_pin>' . $store_pin . '</store_pin>
						<term>' . $terms . '</term>
						<rating_postcode><![CDATA[' . $customer_postcode . ']]></rating_postcode>
						<items>';

				$items = '';

				$product_id   = $cart_value['product_id'];
				$cost         = $product_price;
				$product_name = $product->get_title();

				$api_category = 'Jewellery';
				foreach ( $categories as $term_id ) {
					$term_category = get_term_by( 'id', $term_id, 'product_cat' );

					if ( false !== stripos( $term_category->name, 'watch' ) ) {
						$api_category = 'Watch';
						break;
					}
				}

				if ( 'Watch' === $api_category && 1501 < $cost ) {
					return;
				} elseif ( 'Jewellery' === $api_category && 5001 < $cost ) {
					return;
				}

				$items = '<item>
					<id>' . $product_id . '</id>
					<description><![CDATA[' . $product_name . ']]></description>
					<cost>' . $cost . '</cost>
					<value>' . $cost . '</value>
					<category>' . $api_category . '</category>
					<include>true</include>
				</item>';

				$request_xml = $request_xml . $items . '
					</items>
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

				$entries         = $xpath->query( '//quotation/total' );
				$total_quotation = 0;
				if ( null !== $entries && $entries->length > 0 ) {
					$total_quotation = $entries[0]->nodeValue;
				}

				?>
					</br>
					<input type="hidden" id="thm_quote_value[]" name="thm_quote_value[]" value="<?php echo esc_attr( $total_quotation ); ?>"/>
					<label for="thm_insurance_term_<?php echo esc_attr( $cart_item_key ); ?>"><?php echo esc_html__( 'Insurance Term:', 'thm-integration' ); ?></label>
					<select
						id="thm_insurance_term_<?php echo esc_attr( $cart_item_key ); ?>"
						name="thm_insurance_term_<?php echo esc_attr( $cart_item_key ); ?>"
						class="thm_insurance_term">
						<option value="1" <?php echo esc_attr( $selected_one ); ?>>1 year</option>
						<option value="3" <?php echo esc_attr( $selected_three ); ?>>3 years</option>
					</select>
					</br>
					<input
						type="checkbox"
						id="thm_add_insurance[]"
						name="thm_add_insurance[]"
						class="thm_add_insurance"
						data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
						data-quotation="<?php echo esc_attr( $total_quotation ); ?>"
						<?php echo esc_attr( $checked ); ?>/>
					<label><img src="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/img/thmarch_logo.png" class="thm-logo">
						<?php
						if ( '' !== $checked ) {
							$term_selected = ( '' !== $selected_one ) ? '1 year' : '3 years';
							/* translators: 1: Term 2: currency symbol 3: quotation */
							echo wp_kses_post( sprintf( __( '%1$1s insurance added, <strong>%2$2s%3$3s</strong>', 'thm-integration' ), $term_selected, get_woocommerce_currency_symbol(), $total_quotation ) );
						} else {
							/* translators: 1: currency symbol 2: quotation */
							echo wp_kses_post( sprintf( __( 'Add theft, damage & loss insurance from <strong>%1$1s%2$2s</strong>', 'thm-integration' ), get_woocommerce_currency_symbol(), $total_quotation ) );
						}
						?>
					</label>
				<?php
			}
		}

		/**
		 * Add modal to show the terms of Insurance.
		 *
		 * @return void
		 */
		public function thm_add_terms_modal() {
			?>

				<!-- The Modal -->
				<div id="thm_insurance_modal" class="thm-modal">

					<!-- Modal content -->
					<div class="thm-modal-content">
						<span class="thm-close">&times;</span>

						<img class="thmarch-logo" alt="thmarch logo" src="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/img/thmarch.png">

						<h3><?php esc_html_e( 'Customer Product Information', 'thm-integration' ); ?></h3>

						<p><?php esc_html_e( 'This insurance is optional and will meet the demands and needs of a UK resident requiring insurance for jewellery or watches in the United Kingdom, Channel Islands and Isle of Man plus up to 30 consecutive days elsewhere in the world any one trip.', 'thm-integration' ); ?></p>

						<p><?php esc_html_e( 'The insurance is administered by T H March & Co. Limited, Hare Park House, Yelverton Business Park, Yelverton, Devon, PL20 7LS, and they are authorised and regulated by the Financial Conduct Authority (FCA). This may be checked on the Financial Services Register on the FCA’s website.', 'thm-integration' ); ?></p>

						<p><?php esc_html_e( 'In the unlikely event that you may wish to make a complaint, this should be directed to T H March. The complaints procedure is detailed in your insurance documentation.', 'thm-integration' ); ?></p>

						<p><?php esc_html_e( 'It is important you read the certificate as this details the terms, conditions and any restrictions in cover.', 'thm-integration' ); ?></p>

						<h3><?php esc_html_e( 'Add theft, damage &amp; loss insurance from T H March', 'thm-integration' ); ?></h3>

						<div>

							<i><?php esc_html_e( 'Insurance Premium is inclusive of IPT.', 'thm-integration' ); ?></i>

							<p><?php esc_html_e( 'Our UK customers can insure their purchase with MarchGuard cover from T H March, providers of specialist jewellery and watch insurance since 1887.', 'thm-integration' ); ?></p>

							<div class="thm-accordion">
								<h4 class="header-reset"><?php esc_html_e( 'Main Policy Benefits', 'thm-integration' ); ?></h4>
							</div>

							<div class="thm-panel">
								<ul>
									<li>
										<strong><?php esc_html_e( 'No excess on claims', 'thm-integration' ); ?></strong>
									</li>
									<li><?php esc_html_e( 'Simple and easy to arrange', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'Accidental loss*', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'Accidental damage*', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'Theft cover*', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'Worldwide cover (up to 30  consecutive days abroad)', 'thm-integration' ); ?></li>
									<li><?php 
									// translators: %s Store Name.
									echo  wp_kses_post( sprintf( __( 'All claims are processed through %s (repair or replacement)', 'thm-integration' ), get_option( 'wc_store_name', '' ) ) );
									?></li>
									<li><?php esc_html_e( 'For 3 year policies, protection is included against price inflation on replacement items (see certificate terms for details)', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( '* Subject to terms and conditions of the policy', 'thm-integration' ); ?></li>
								</ul>

								<a class="insurance-pdf" download href="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/pdf/Joshuas_internet_wording.pdf">
									<?php esc_html_e( 'Click here to download the full policy wording document (PDF)', 'thm-integration' ); ?>
								</a>
								<a class="insurance-pdf" download href="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/pdf/March_Guard_IPID.pdf">
									<?php esc_html_e( 'Click here to download the insurance product information document (PDF)', 'thm-integration' ); ?>
								</a>
								<a class="insurance-pdf" download href="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/pdf/Customer_Product_Information.pdf">
									<?php esc_html_e( 'Click here to download the customer product information document (PDF)', 'thm-integration' ); ?>
								</a>
							</div>

							</br>

							<div class="thm-accordion">
								<h4 class="header-reset"><?php esc_html_e( 'Please be aware:', 'thm-integration' ); ?></h4>
							</div>

							<div class="thm-panel">
								<ul>
									<li><?php esc_html_e( 'UK residents only', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'No cover for wear and tear', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'No cover for items left in unattended baggage or unattended vehicles', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'No cover for free gifts or accessories', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'There is a 14-day cooling off period. For goods returned under our 30-day return policy, only a pro-rated refund can be made for months remaining after this period.', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'This insurance is NOT renewable after the initial term', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'It is not possible to mix 1 year and 3 year terms on the same policy', 'thm-integration' ); ?></li>
									<li><?php esc_html_e( 'See policy wording for full details of cover and exclusions', 'thm-integration' ); ?></li>
								</ul>
							</div>

							</br>

							<div class="thm-accordion">
								<h4 class="header-reset"><strong><?php esc_html_e( 'Terms & Conditions', 'thm-integration' ); ?></strong></h4>
							</div>

							<div class="thm-panel">
								<p><?php esc_html_e( 'It is important you read the certificate as this details the terms, conditions and any restrictions in cover.', 'thm-integration' ); ?></p>

								<a class="insurance-pdf" download href="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/pdf/Joshuas_internet_wording.pdf">
									<?php esc_html_e( 'Click here to download the full policy wording document (PDF)', 'thm-integration' ); ?>
								</a>
								<a class="insurance-pdf" download href="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/pdf/March_Guard_IPID.pdf">
									<?php esc_html_e( 'Click here to download the insurance product information document (PDF)', 'thm-integration' ); ?>
								</a>
								<!--<a class="insurance-pdf" download href="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/pdf/Customer_Product_Information.pdf">
									<?php esc_html_e( 'Click here to download the customer product information document (PDF)', 'thm-integration' ); ?>
								</a>-->
							</div>

							</br>

							<p>
								<strong><?php esc_html_e( 'Important notice: ', 'thm-integration' ); ?></strong>
								<?php esc_html_e( 'Some household policies may have restrictions on watch and jewellery cover for trips abroad. They may also ask you to use a jeweller of their choosing for repair or replacement. In the event of a household insurance claim, you may be expected to pay a policy excess and any claims made may affect your future premiums.', 'thm-integration' ); ?></p>
							<div class="thm-buttons-footer centered">
								<div class="thm-close-btn thm-width-50">
									<a class="info-return button btn-ghost">Close</a>
								</div>
								<div class="thm-accept-btn thm-width-50">
									<a class="action button alt thm-accept-insurance" id="info-accept"><?php esc_html_e( 'Accept information', 'thm-integration' ); ?></a>
								</div>
							</div>

							<!--<input type="hidden" id="thm_insurance_quote" name="thm_insurance_quote" class="thm_insurance_quote" />
							<input type="hidden" id="thm_cart_item_key" name="thm_cart_item_key" class="thm_cart_item_key" />-->
						</div>
					</div>

				</div>

			<?php
		}

		/**
		 * Hidden variables for managing insurance
		 *
		 * @return void
		 */
		public function thm_hidden_variables() {
			?>

				<input type="hidden" id="thm_insurance_quote" name="thm_insurance_quote" class="thm_insurance_quote" />
				<input type="hidden" id="thm_cart_item_key" name="thm_cart_item_key" class="thm_cart_item_key" />
				<input type="hidden" id="thm_insurance_term_hidden" name="thm_insurance_term_hidden" class="thm_insurance_term_hidden" />

			<?php
		}

		/**
		 * Enqueue Styles.
		 *
		 * @return void
		 */
		public function thm_enqueue_styles() {
			if ( is_cart() ) {
				$this->thm_scripts();
			}
		}

		/**
		 * Scripts needed for modal and checkbox stuff.
		 *
		 * @return void
		 */
		public function thm_scripts() {
			wp_enqueue_style(
				'thm-styles',
				TH_PLUGIN_URL . '/assets/css/frontend.css',
				'',
				TH_PLUGIN_VERSION,
				false
			);

			wp_enqueue_script(
				'thm-frontend',
				TH_PLUGIN_URL . '/assets/js/frontend.js',
				array(),
				TH_PLUGIN_VERSION,
				true
			);
		}

		/**
		 * Update Fees on cart page.
		 *
		 * @return void
		 */
		public function thm_update_fees() {
			wp_send_json( array( 'message' => 'Settings are saved!!!' ) );
		}

		/**
		 * Update insurance amount to the cart totals.
		 *
		 * @param mixed $cart Cart Object.
		 * @return void
		 */
		public function thm_update_insurance_fee( $cart ) {

			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				return;
			}

			if ( isset( $_POST['thm_cart_item_key'] ) && '' !== $_POST['thm_cart_item_key'] && isset( $_POST['thm_insurance_quote'] ) && '0' === $_POST['thm_insurance_quote'] && null !== WC()->session->get( 'thm_insurance' ) ) {

				$existing_session = WC()->session->get( 'thm_insurance' );
				unset( $existing_session[ $_POST['thm_cart_item_key'] ] );

				$existing_fees = 0;
				foreach ( $existing_session as $cart_key => $insurance_details ) {
					$existing_fees = (float) $existing_fees + (float) $insurance_details['fee'];
				}

				if ( 0 !== $existing_fees ) {
					$fees = WC()->cart->get_fees();
					foreach ( $fees as $key => $fee ) {
						if ( 'MarchGuard Insurance' === $fees[ $key ]->name ) {
							unset( $fees[ $key ] );
						}
					}

					WC()->cart->fees_api()->set_fees( $fees );

					if ( 0 !== $existing_fees ) {
						$cart->add_fee( 'MarchGuard Insurance', $existing_fees, false );

						WC()->session->set(
							'thm_insurance',
							$existing_session
						);

						WC()->session->set(
							'thm_insurance',
							$existing_session
						);
					}
				} else {
					$fees = WC()->cart->get_fees();
					foreach ( $fees as $key => $fee ) {
						if ( 'MarchGuard Insurance' === $fees[ $key ]->name ) {
							unset( $fees[ $key ] );
						}
					}

					WC()->cart->fees_api()->set_fees( $fees );
					WC()->session->__unset( 'thm_insurance' );
				}
			} elseif ( ( isset( $_POST['thm_cart_item_key'] ) && '' !== $_POST['thm_cart_item_key'] ) || null !== WC()->session->get( 'thm_insurance' ) ) {

				$existing_fees    = 0;
				$existing_session = array();
				if ( null !== WC()->session->get( 'thm_insurance' ) ) {
					foreach ( WC()->session->get( 'thm_insurance' ) as $cart_key => $insurance_details ) {
						$existing_fees = $existing_fees + (float) $insurance_details['fee'];
					}
					$existing_session = WC()->session->get( 'thm_insurance' );
				}

				$fee = 0;
				if ( isset( $_POST['thm_insurance_quote'] ) && '' !== $_POST['thm_insurance_quote'] ) {
					$fee = (float) $_POST['thm_insurance_quote'];

					$term = isset( $_POST[ 'thm_insurance_term_' . $_POST['thm_cart_item_key'] ] ) ? $_POST[ 'thm_insurance_term_' . $_POST['thm_cart_item_key'] ] : 1;

					if ( isset( $_POST['thm_cart_item_key'] ) && '' !== $_POST['thm_cart_item_key'] ) {
						$cart_contents = $cart->cart_contents;

						$cart_item_value = $cart_contents[ $_POST['thm_cart_item_key'] ];
						$cart_item_qty   = $cart_item_value['quantity'];
						$fee             = $cart_item_qty * $fee;

						$existing_session[ $_POST['thm_cart_item_key'] ] = array(
							'fee'  => $fee,
							'term' => $term,
						);
					}
				} elseif ( isset( $_POST['update_cart'] ) && '' !== $_POST['update_cart'] && null !== WC()->session->get( 'thm_insurance' ) ) {
					$cart_session     = $_POST['cart'];
					$existing_session = WC()->session->get( 'thm_insurance' );
					$fee              = 0;
					$counter          = 0;

					foreach ( $cart_session as $cart_key => $cart_value ) {
						if ( isset( $_POST['thm_insurance_term_' . $cart_key] ) && '' !== $_POST['thm_insurance_term_' . $cart_key] ) {
							if ( array_key_exists( $cart_key, $existing_session ) ) {
								$existing_session[ $cart_key ]['fee'] = $_POST['thm_quote_value'][ $counter ] * $cart_value['qty'];

								$fee = $_POST['thm_quote_value'][ $counter ] * $cart_value['qty'];
							}
							$counter++;
						}
					}

					if ( 0 !== $fee ) {
						$cart->add_fee( 'MarchGuard Insurance', $fee, false );

						WC()->session->set(
							'thm_insurance',
							$existing_session
						);
					}
				}

				if ( 0 !== $existing_fees ) {
					$fee = $fee + $existing_fees;
				}

				if ( 0 !== $fee ) {
					$cart->add_fee( 'MarchGuard Insurance', $fee, false );

					WC()->session->set(
						'thm_insurance',
						$existing_session
					);
				}
			}
		}

		/**
		 * Shortcode callback. Shortcode usage [thm_insurance_details]
		 *
		 * @return void
		 */
		public function thm_shortcode_callback() {
			$store_id  = get_option( 'wc_store_id', '' );
			$store_pin = get_option( 'wc_store_pin', '' );

			$cart_item_key = '';

			global $product;

			$product_id = $product->get_id();

			$categories   = $product->get_category_ids();
			$allowed_cats = get_option( 'wc_thm_categories', array() );

			if ( 0 === count( $allowed_cats ) ) {
				return;
			}

			$allowed = ! empty( array_intersect( $categories, $allowed_cats ) );

			if ( 'yes' === get_option( 'wc_thm_enable', '' ) && '' !== $store_id && '' !== $store_pin && 5001 > $product->get_regular_price() && $allowed ) {
				add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'thm_hidden_variables' ) );
				$this->thm_scripts();

				$customer_postcode = WC()->customer->get_billing_postcode();

				if ( '' === $customer_postcode ) {
					$customer_postcode = get_option( 'woocommerce_store_postcode', 'CH41 2PX' );
				}

				$terms = ( isset( $_GET['term'] ) && '' !== $_GET['term'] ) ? $_GET['term'] : '1';

				$selected_one   = '';
				$selected_three = '';

				if ( '1' === $terms ) {
					$selected_one = 'selected="selected"';
				} elseif ( '3' === $terms ) {
					$selected_three = 'selected="selected"';
				}

				$checked = '';

				$request_xml = '
					<quotation_request>
						<store_id>' . $store_id . '</store_id>
						<store_pin>' . $store_pin . '</store_pin>
						<term>' . $terms . '</term>
						<rating_postcode><![CDATA[' . $customer_postcode . ']]></rating_postcode>
						<items>';

				$items = '';

				if ( 'variable' === $product->get_type() ) {
					$cost = $product->get_variation_regular_price( 'max' );
				} else {
					$cost = $product->get_regular_price();
				}
				$product_name = $product->get_title();

				$api_category = 'Jewellery';
				foreach ( $categories as $term_id ) {
					$term_category = get_term_by( 'id', $term_id, 'product_cat' );

					if ( false !== stripos( $term_category->name, 'watch' ) ) {
						$api_category = 'Watch';
						break;
					}
				}

				if ( 'Watch' === $api_category && 1501 < $cost ) {
					return;
				} elseif ( 'Jewellery' === $api_category && 5001 < $cost ) {
					return;
				}

				$items = '<item>
					<id>' . $product_id . '</id>
					<description><![CDATA[' . $product_name . ']]></description>
					<cost>' . $cost . '</cost>
					<value>' . $cost . '</value>
					<category>' . $api_category . '</category>
					<include>true</include>
				</item>';

				$request_xml = $request_xml . $items . '
					</items>
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

				$entries = $xpath->query( '//quotation/total' );

				if ( null !== $entries && $entries->length > 0 ) {
					$total_quotation = $entries[0]->nodeValue;
				}

				ob_start();

				?>
					</br>
					<input type="hidden" id="thm_quote_value" name="thm_quote_value" value="<?php echo esc_attr( $total_quotation ); ?>"/>
					<label for="thm_insurance_term_<?php echo esc_attr( $cart_item_key ); ?>"><?php echo esc_html__( 'Insurance Term:', 'thm-integration' ); ?></label>
					<select
						id="thm_insurance_term_<?php echo esc_attr( $cart_item_key ); ?>"
						name="thm_insurance_term_<?php echo esc_attr( $cart_item_key ); ?>"
						class="thm_insurance_term">
						<option value="1" <?php echo esc_attr( $selected_one ); ?>>1 year</option>
						<option value="3" <?php echo esc_attr( $selected_three ); ?>>3 years</option>
					</select>
					</br>
					<input
						type="checkbox"
						id="thm_add_insurance"
						name="thm_add_insurance"
						class="thm_add_insurance"
						data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
						data-quotation="<?php echo esc_attr( $total_quotation ); ?>"/>
					<label for="thm_add_insurance"><img src="<?php echo esc_url( TH_PLUGIN_URL ); ?>/assets/img/thmarch_logo.png" class="thm-logo">
						<?php
							/* translators: 1: currency symbol 2: quotation */
							echo wp_kses_post( sprintf( __( 'Add theft, damage & loss insurance from <strong>%1$1s%2$2s</strong>', 'thm-integration' ), get_woocommerce_currency_symbol(), $total_quotation ) );
						?>
					</label>
				<?php

				self::thm_add_terms_modal();

				$output = ob_get_clean();
				return $output;
			}
		}

		/**
		 * After Add to cart action to add insurance quote.
		 *
		 * @param string $cart_item_key Cart Item Key.
		 * @return void
		 */
		public function thm_add_insurance_from_product( $cart_item_key ) {
			if ( isset( $_POST['thm_insurance_quote'] ) && '' !== $_POST['thm_insurance_quote'] ) {
				$existing_fees    = 0;
				$existing_session = array();
				if ( null !== WC()->session->get( 'thm_insurance' ) ) {
					$existing_session = WC()->session->get( 'thm_insurance' );
				}

				$fee = 0;
				if ( isset( $_POST['thm_insurance_quote'] ) && '' !== $_POST['thm_insurance_quote'] ) {
					$fee = (float) $_POST['thm_insurance_quote'];

					if ( '' !== $cart_item_key ) {
						$existing_session[ $cart_item_key ] = array(
							'fee'  => $fee,
							'term' => $_POST[ 'thm_insurance_term_hidden' ],
						);
					}
				}

				if ( 0 !== $fee ) {
					WC()->session->set(
						'thm_insurance',
						$existing_session
					);
				}
			}
		}

		/**
		 * Remove session set when item removed.
		 *
		 * @param string  $cart_item_key Cart Item Key.
		 * @param WC_Cart $cart_instance Cart Instance.
		 * @return void
		 */
		public function thm_cart_item_removed( $cart_item_key, $cart_instance ) {
			if ( stripos( wp_json_encode( WC()->session->get( 'thm_insurance' ) ), $cart_item_key ) ) {
				$_POST['thm_cart_item_key']   = $cart_item_key;
				$_POST['thm_insurance_quote'] = '0';
			}
		}
	}

}

return new THMarch_Frontend();

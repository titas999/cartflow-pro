<?php
/**
 * Offer markup.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Offer Markup
 *
 * @since 1.0.0
 */
class Cartflows_Pro_Base_Offer_Markup {


	/**
	 * Member Variable
	 *
	 * @var object instance
	 */
	private static $instance;

	/**
	 *  Initiator
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 *  Constructor
	 */
	public function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'offer_scripts' ) );
		$this->load_instant_layout_actions();
	}

	/**
	 * Load instant layout actions.
	 *
	 * This method is responsible for loading actions related to the instant layout feature.
	 * It checks if the current page is a step post type and if the instant layout is enabled for the flow.
	 * If both conditions are met, it adds filters to modify the page template file, description, and stock HTML.
	 */
	public function load_instant_layout_actions() {
		// Instant offer template file.
		add_filter( 'cartflows_page_template_file', array( $this, 'cartflows_instant_offer_page_template_file' ), 10, 1 );
		add_filter( 'woocommerce_get_stock_html', array( $this, 'modify_string_for_availibility_text' ), 10, 2 );
	}

	/**
	 *  Offer script
	 */
	public function offer_scripts() {

		if ( _is_wcf_base_offer_type() ) {
			global $post;

			$product_id = '';
			$step_id    = $post->ID;
			$flow_id    = wcf()->utils->get_flow_id_from_step_id( $step_id );

			if ( ( ! empty( $flow_id ) && Cartflows_Helper::is_instant_layout_enabled( (int) $flow_id ) ) || Cartflows_Pro_Helper::is_instant_layout_enabled_for_step( $step_id ) ) {

				wp_enqueue_style( 'wcf-instant-offer', wcf_pro()->utils->get_css_url( 'instant-offer-styles' ), '', CARTFLOWS_PRO_VER );

				add_action( 'body_class', array( $this, 'add_body_class_for_instant_offer' ), 10, 1 );
				add_filter( 'woocommerce_dropdown_variation_attribute_options_args', array( $this, 'variation_attribute_options_args' ), 10, 1 );
				add_action( 'wp_footer', array( $this, 'add_placeholder_to_quantity_field' ) );

				$offer_steps_dynamic_css = apply_filters( 'cartflows_offer_steps_enable_dynamic_css', true );

				if ( $offer_steps_dynamic_css ) {
					$offer_steps_page_id = $post->ID;

					$style = get_post_meta( $offer_steps_page_id, 'wcf-dynamic-css', true );

					$css_version = get_post_meta( $offer_steps_page_id, 'wcf-dynamic-css-version', true );
					if ( empty( $style ) || CARTFLOWS_ASSETS_VERSION !== $css_version ) {
						$style = $this->generate_offer_step_style();
						update_post_meta( $offer_steps_page_id, 'wcf-dynamic-css', wp_slash( $style ) );
						update_post_meta( $offer_steps_page_id, 'wcf-dynamic-css-version', CARTFLOWS_ASSETS_VERSION );
					}

					CartFlows_Font_Families::render_fonts( $offer_steps_page_id );
					wp_add_inline_style( 'wcf-instant-offer', $style );
				}
			}

			if ( wcf()->flow->is_flow_testmode( $flow_id ) ) {

				$offer_product = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-product', 'dummy' );

				if ( 'dummy' === $offer_product ) {

					$args = array(
						'posts_per_page' => 1,
						'orderby'        => 'rand',
						'post_type'      => 'product',
					);

					$random_product = get_posts( $args );

					if ( isset( $random_product[0]->ID ) ) {
						$offer_product = array(
							$random_product[0]->ID,
						);
					}
				}
			} else {
				$offer_product = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-product' );
			}

			if ( isset( $offer_product[0] ) ) {

				$product_id = $offer_product[0];
			}

			$order_id   = ( isset( $_GET['wcf-order'] ) ) ? intval( $_GET['wcf-order'] ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_key  = ( isset( $_GET['wcf-key'] ) ) ? sanitize_text_field( wp_unslash( $_GET['wcf-key'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order      = wc_get_order( $order_id );
			$skip_offer = 'no';
			$offer_type = get_post_meta( $step_id, 'wcf-step-type', true );

			$payment_method = '';
			if ( $order ) {

				$payment_method = $order->get_payment_method();

				$gateways = array( 'paypal', 'ppec_paypal' );
				$gateways = apply_filters( 'cartflows_offer_supported_payment_gateway_slugs', $gateways );
				if ( ( in_array( $payment_method, $gateways, true ) ) && ! wcf_pro()->utils->is_reference_transaction() && ! wcf_pro()->utils->is_zero_value_offered_product() ) {

					$skip_offer = 'yes';
				}
			}

			$currency_symbol    = get_woocommerce_currency_symbol();
			$discount_type      = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-discount' );
			$discount_value     = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-discount-value' );
			$flat_shipping_rate = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-flat-shipping-value' );

			$localize = array(
				'step_id'                     => $step_id,
				'product_id'                  => $product_id,
				'order_id'                    => $order_id,
				'order_key'                   => $order_key,
				'skip_offer'                  => $skip_offer,
				'offer_type'                  => $offer_type,
				'discount_type'               => $discount_type,
				'discount_value'              => $discount_value,
				'flat_shipping_rate'          => $flat_shipping_rate,
				'currency_symbol'             => $currency_symbol,
				'wcf_downsell_accepted_nonce' => wp_create_nonce( 'wcf_downsell_accepted' ),
				'wcf_downsell_rejected_nonce' => wp_create_nonce( 'wcf_downsell_rejected' ),
				'wcf_upsell_accepted_nonce'   => wp_create_nonce( 'wcf_upsell_accepted' ),
				'wcf_upsell_rejected_nonce'   => wp_create_nonce( 'wcf_upsell_rejected' ),
				'payment_method'              => $payment_method,
			);

			if ( 'stripe' === $payment_method ) {
				$localize['wcf_stripe_sca_check_nonce'] = wp_create_nonce( 'wcf_stripe_sca_check' );
				wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
				wp_enqueue_script( 'stripe' );
			}

			if ( 'cpsw_stripe' === $payment_method ) {
				$localize['wcf_cpsw_create_payment_intent_nonce'] = wp_create_nonce( 'wcf_cpsw_create_payment_intent' );
			}

			if ( 'cpsw_stripe_element' === $payment_method ) {
				$localize['wcf_cpsw_create_payment_element_intent_nonce'] = wp_create_nonce( 'wcf_cpsw_create_element_payment_intent' );
			}

			if ( 'mollie_wc_gateway_creditcard' === $payment_method ) {
				$localize['wcf_mollie_creditcard_process_nonce'] = wp_create_nonce( 'wcf_mollie_creditcard_process' );
			}

			if ( 'mollie_wc_gateway_ideal' === $payment_method ) {
				$localize['wcf_mollie_ideal_process_nonce'] = wp_create_nonce( 'wcf_mollie_ideal_process' );
			}

			if ( 'ppcp-gateway' === $payment_method ) {
				$localize['wcf_create_paypal_order_nonce']  = wp_create_nonce( 'wcf_create_paypal_order' );
				$localize['wcf_capture_paypal_order_nonce'] = wp_create_nonce( 'wcf_capture_paypal_order' );
			}

			if ( 'woocommerce_payments' === $payment_method ) {
				$localize['wcf_woop_create_payment_intent_nonce'] = wp_create_nonce( 'wcf_woop_create_payment_intent' );
				wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
				wp_enqueue_script( 'stripe' );
			}

			if ( 'cppw_paypal' === $payment_method ) {
				$localize['wcf_cppw_create_paypal_order_nonce'] = wp_create_nonce( 'wcf_cppw_create_paypal_order' );
			}

			$localize = apply_filters( 'cartflows_offer_js_localize', $localize );

			$localize_script  = '<!-- script to print the admin localized variables -->';
			$localize_script .= '<script type="text/javascript">';
			$localize_script .= 'var cartflows_offer = ' . wp_json_encode( $localize ) . ';';
			$localize_script .= '</script>';

			// Reason for PHPCS ignore: Used to localize the strings which will be displayed on the browser's tab.
			echo $localize_script; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Offer accepeted
	 *
	 * @param int   $step_id Flow step id.
	 * @param array $extra_data extra data.
	 * @param array $result process result.
	 * @since 1.0.0
	 */
	public function offer_accepted( $step_id, $extra_data, $result ) {

		wcf()->logger->log( 'Start-' . __CLASS__ . '::' . __FUNCTION__ );

		// Update the offer data changes made on offer page, like quantity and variation.
		$order = wc_get_order( $extra_data['order_id'] );
		if ( is_object( $order ) ) {
			$updated_offer_data = $order->get_meta( 'wcf_offer_product_data_' . $step_id, true );
			if ( is_array( $updated_offer_data ) && ! empty( $updated_offer_data ) ) {
				$extra_data = array_merge( $extra_data, $updated_offer_data );
			}
		}

		$order_id          = $extra_data['order_id'];
		$order_key         = $extra_data['order_key'];
		$product_id        = $extra_data['product_id'];
		$variation_id      = $extra_data['variation_id'];
		$input_qty         = $extra_data['input_qty'];
		$step_type         = $extra_data['template_type'];
		$is_charge_success = false;
		$order             = wc_get_order( $order_id );

		$offer_product = wcf_pro()->utils->get_offer_data( $step_id, $variation_id, $input_qty, $order_id );

		$is_offer_accepted = $order->get_meta( 'wcf_is_offer_purchased', true );

		if ( empty( $order_key ) ) {
			$data          = array(
				'order_id'  => $order_id,
				'order_key' => $order_key,
			);
			$next_step_url = wcf_pro()->flow->get_next_step_url( $step_id, $data );

			$result = array(
				'status'   => 'failed',
				'redirect' => $next_step_url,
				'message'  => __( 'Order does not exist', 'cartflows-pro' ),
			);

			wcf()->logger->log( 'Order-' . $order_id . ' ' . $step_type . ' Offer Payment Failed. Order key does not exist. Redirected to next step.' );

			return $result;
		}

		if ( ! empty( $is_offer_accepted ) ) {

			$is_offer_accepted = json_decode( $is_offer_accepted, true );

			if (
				$order_id === $is_offer_accepted['offer_order_id'] &&
				(
					$step_id === $is_offer_accepted['offer_step_id'] &&
					$order_key === $is_offer_accepted['offer_order_key']
				) &&
				'offer_accepted' === $is_offer_accepted['offer_status']
			) {

				/* Get Redirect URL */
				$next_step_url = wcf_pro()->flow->get_next_step_url(
					$step_id,
					array(
						'action'        => 'offer_accepted',
						'order_id'      => $order_id,
						'order_key'     => $order_key,
						'template_type' => $step_type,
					)
				);

				$result = array(
					'status'   => 'failed',
					'redirect' => $next_step_url,
					'message'  => __( 'Seems like this order is been already purchased.', 'cartflows-pro' ),
				);

				wcf()->logger->log( 'Offer is already purchased. Redirecting to URL : ' . $next_step_url );
				wcf()->logger->log( 'Use-case: The user might have clicked the back button on browser and trying to re-purchase the offer.', 'cartflows-pro' );

				return $result;
			}
		}

		// check if product is in stock if stock management is enabled.

		$product        = wc_get_product( $product_id );
		$stock_quantity = $product ? $product->get_stock_quantity() : 0;

		if ( $product && $product->managing_stock() && $stock_quantity < intval( $offer_product['qty'] ) ) {

			$data = array(
				'order_id'  => $order_id,
				'order_key' => $order_key,
			);

			// @todo Need some conditional redirection if there is downsell.
			$next_step_url = wcf_pro()->flow->get_next_step_url( $step_id, $data );

			$result = array(
				'status'   => 'failed',
				'redirect' => $next_step_url,
				'message'  => __( 'Oooops! Product is out of stock.', 'cartflows-pro' ),
			);

			wcf()->logger->log( 'Order-' . $order_id . ' ' . $step_type . ' Offer Payment Failed. Product is out of stock. Redirected to next step.' );

			return $result;
		}

		// Restrict the purchase of product if the product price is in negative.
		if ( isset( $offer_product['price'] ) && ( floatval( $offer_product['price'] ) < floatval( 0 ) ) ) {

			$data = array(
				'order_id'  => $order_id,
				'order_key' => $order_key,
			);

			// @todo Need some conditional redirection if there is downsell.
			$next_step_url = wcf_pro()->flow->get_next_step_url( $step_id, $data );

			$result = array(
				'status'   => 'failed',
				'redirect' => $next_step_url,
				'message'  => __( 'Oooops! Product\'s price is not correct.', 'cartflows-pro' ),
			);

			wcf()->logger->log( 'Order-' . $order_id . ' ' . $step_type . ' Offer Payment Failed. Product price is in negative format. Redirected to next step.' );

			return $result;
		}

		if ( isset( $offer_product['price'] ) && ( floatval( 0 ) === floatval( $offer_product['price'] ) || '' === trim( $offer_product['price'] ) ) ) {

			$is_charge_success = true;
		} else {

			$order_gateway = $order->get_payment_method();

			wcf()->logger->log( 'Order-' . $order->get_id() . ' ' . $order_gateway . ' - Payment gateway' );

			$gateway_obj = wcf_pro()->gateways->load_gateway( $order_gateway );

			if ( $gateway_obj ) {

				wcf()->logger->log( 'Order-' . $order->get_id() . ' Payment gateway charge' );
				$offer_product['action'] = $extra_data['action'];
				$is_charge_success       = $gateway_obj->process_offer_payment( $order, $offer_product );
			}
		}

		wcf()->logger->log( 'Is Charge Success - ' . $is_charge_success );

		if ( $is_charge_success ) {

			if ( 'upsell' === $step_type ) {
				/* Add Product To Main Order */
				wcf_pro()->order->add_upsell_product( $order, $offer_product );

			} else {
				wcf_pro()->order->add_downsell_product( $order, $offer_product );
			}

			do_action( 'cartflows_offer_accepted', $order, $offer_product );
			do_action( 'cartflows_' . $step_type . '_offer_accepted', $order, $offer_product );

			/**
			 * We need to reduce stock here.
			 *
			 * @todo
			 * reduce_stock();
			 */

			$data = array(
				'action'        => 'offer_accepted',
				'order_id'      => $order_id,
				'order_key'     => $order_key,
				'template_type' => $step_type,
			);

			/* Get Redirect URL */
			$next_step_url = wcf_pro()->flow->get_next_step_url( $step_id, $data );

			$result = array(
				'status'   => 'success',
				'redirect' => $next_step_url,
				'message'  => wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-order-success-text' ),
			);

			// Set meta-option to verify that this offer is accepted or rejected.
			$this->update_order_and_step_status(
				$order,
				array(
					'offer_status'    => $data['action'],
					'offer_step_id'   => $step_id,
					'offer_order_id'  => $order_id,
					'offer_order_key' => $order_key,
				)
			);

			wcf()->logger->log( 'Redirect URL : ' . $next_step_url );
			wcf()->logger->log( 'Order-' . $order_id . ' ' . $step_type . ' Offer accepted for step ID : ' . $step_id );
		} else {

			/* @todo if payment failed redirect to last page or not */
			$data = array(
				'order_id'  => $order_id,
				'order_key' => $order_key,
			);

			$thank_you_page_url = wcf_pro()->flow->get_thankyou_page_url( $step_id, $data );

			$result = array(
				'status'   => 'failed',
				'redirect' => $thank_you_page_url,
				'message'  => wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-order-failure-text' ),
			);

			wcf()->logger->log( 'Order-' . $order_id . ' ' . $step_type . ' Offer Payment Failed. Redirected to thankyou step.' );
		}

		wcf()->logger->log( 'End-' . __CLASS__ . '::' . __FUNCTION__ );

		return $result;
	}

	/**
	 * Offer rejected
	 *
	 * @param int   $step_id Flow step id.
	 * @param array $extra_data extra data.
	 * @param array $result process result.
	 * @since 1.0.0
	 */
	public function offer_rejected( $step_id, $extra_data, $result ) {

		/* Get Redirect URL */
		$next_step_url = wcf_pro()->flow->get_next_step_url( $step_id, $extra_data );

		$order_id  = $extra_data['order_id'];
		$step_type = $extra_data['template_type'];

		$order         = wc_get_order( $order_id );
		$offer_product = wcf_pro()->utils->get_offer_data( $step_id );

		$result = array(
			'status'   => 'success',
			'redirect' => $next_step_url,
			'message'  => __( 'Redirecting...', 'cartflows-pro' ),
		);

		wcf()->logger->log( 'Order-' . $order_id . ' ' . $step_type . ' Offer rejected' );

		do_action( 'cartflows_offer_rejected', $order, $offer_product );
		do_action( 'cartflows_' . $step_type . '_offer_rejected', $order, $offer_product );

		return $result;
	}

	/**
	 * Offer rejected
	 *
	 * @param int    $step_id Flow step id.
	 * @param object $order order data.
	 */
	public function maybe_skip_offer( $step_id, $order ) {

		$is_skip_offer = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-skip-offer', false );
		$billing_email = $order->get_billing_email();

		if ( 'yes' === $is_skip_offer && $billing_email ) {

			$offer_product    = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-product', false );
			$offer_product_id = $offer_product[0];

			$is_purchased = wc_customer_bought_product( $billing_email, get_current_user_id(), $offer_product_id );

			if ( $is_purchased ) {

				$wcf_step_obj     = wcf_pro_get_step( $step_id );
				$step_to_redirect = $wcf_step_obj->get_next_step_id();
				$step_type        = wcf_get_step_type( $step_id );

				do_action( 'cartflows_' . $step_type . '_offer_skipped', $step_id, $order, $offer_product );

				/* Get Redirect URL */
				$step_id = $this->get_next_step_id_for_skip_offer( $step_id, $step_to_redirect );

				/**
				 * Check recursively to find if the next step is also upsell/downsell and has the skip offer is enabled.
				 *
				 * Use-case: If there are multiple offer steps and has the same product selected & and Skip offer is enabled.
				 * Output  : The control should return the next step after the offer steps.
				 * Example : Checkout, Upsell-1, Upsell-2, Thank you.
				 */
				if ( wcf()->utils->check_is_offer_page( $step_id ) ) {
					$step_id = $this->maybe_skip_offer( $step_id, $order );
				}           
			}
		}

		return $step_id;
	}

	/**
	 * Get next step id for skipped offers.
	 *
	 * @param int $current_step_id step id.
	 * @param int $step_to_redirect default step id.
	 */
	public function get_next_step_id_for_skip_offer( $current_step_id, $step_to_redirect ) {

		$flow_id = wcf()->utils->get_flow_id_from_step_id( $current_step_id );
		$steps   = get_post_meta( $flow_id, 'wcf-steps', true );

		if ( $steps ) {

			$current_step_found = false;

			foreach ( $steps as $index => $step ) {

				if ( $current_step_found ) {

					if ( in_array( $step['type'], array( 'upsell', 'thankyou' ), true ) ) {

						$step_to_redirect = $step['id'];
						break;
					}
				} elseif ( intval( $step['id'] ) === $current_step_id ) {


						$current_step_found = true;
				}
			}
		}

		return $step_to_redirect;
	}

	/**
	 * Update the order and step status in the step's post-meta.
	 *
	 * @param wc_order $order The current step ID.
	 * @param array    $offer_purchased_data The current offer data.
	 *
	 * @return void
	 */
	public function update_order_and_step_status( $order = '', $offer_purchased_data = '' ) {

		if ( ! empty( $order ) && ! empty( $offer_purchased_data ) ) {
			$order->update_meta_data( 'wcf_is_offer_purchased', wp_json_encode( $offer_purchased_data ) );
		}
	}

	/**
	 * Cartflows instant checkout page template file.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	public function cartflows_instant_offer_page_template_file( $file ) {
		global $post;

		$flow_id = wcf()->utils->is_step_post_type() ? wcf()->utils->get_flow_id() : 0;
		$step_id = isset( $post ) && ! empty( $post->ID ) ? $post->ID : 0;

		$step_id = empty( $step_id ) ? _get_wcf_base_offer_id() : $step_id; 

		if ( empty( $flow_id ) || ! class_exists( 'Cartflows_Helper' ) || ! Cartflows_Helper::is_instant_layout_enabled( (int) $flow_id ) ) {
			return $file;
		}

		if ( _is_wcf_base_offer_type() && Cartflows_Pro_Helper::is_instant_layout_enabled_for_step( $step_id ) ) {
			return CARTFLOWS_PRO_BASE_OFFER_DIR . 'templates/wcf-ic-offer-template.php';
		} else {
			return $file;
		}
	}

	/**
	 * Add body classes for instant offer layout.
	 *
	 * @param array $body_classes The body classes.
	 * @return array $body_classes Modified body added classes/
	 */
	public function add_body_class_for_instant_offer( $body_classes ) {
		$body_classes[] = 'cartflows-instant-checkout';
		return $body_classes;
	}

	/**
	 * Add show_option_none to variation attribute options args.
	 *
	 * @param array $args The variation attribute options args.
	 * @return array $args Modified variation attribute options args.
	 */
	public function variation_attribute_options_args( $args ) {
		$attribute_name           = wc_attribute_label( $args['attribute'] );
		$args['show_option_none'] = $attribute_name;
		return $args;
	}

	/**
	 * Add placeholder to quantity field.
	 *
	 * @return void
	 */
	public function add_placeholder_to_quantity_field() {
		?>
		<script>
			document.addEventListener("DOMContentLoaded", function() {
				document.querySelectorAll("input.qty").forEach(function(input) {
					input.setAttribute("placeholder", "Quantity");
				});
			});
		</script>
		<?php
	}

	/**
	 * Generate Thank You Styles.
	 *
	 * @return string
	 */
	public function generate_offer_step_style() {
		global $post;

		if ( _is_wcf_base_offer_type() ) {
			$offer_step_id = $post->ID;
		} else {
			$offer_step_id = wcf()->utils->get_step_id();
		}
		$output     = '';
		$design_css = array();
		$output    .= $this->get_instant_offer_steps_css( $output, $offer_step_id, $design_css );

		return apply_filters( 'cartflows_offer_setps_generated_styles', $output, $offer_step_id, $design_css );
	}

	/**
	 * Generate Instant Offer Steps CSS.
	 *
	 * @since x.x.x
	 *
	 * @param string $output Already generated CSS.
	 * @param int    $offer_step_id Offer step ID.
	 * @param array  $design_css Design CSS.
	 * @return string $output Modified CSS
	 */
	public function get_instant_offer_steps_css( $output, $offer_step_id, $design_css ) {

		// Default variable inilitilizations.
		$is_design_setting_enabled = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-enable-design-settings' );
		
		$primary_color                   = '';
		$base_font_family                = '';
		$left_section_bg_color           = '';
		$right_section_bg_color          = '';
		$advance_options                 = '';
		$heading_text_color              = '';
		$heading_font_family             = '';
		$heading_font_weight             = '';
		$sub_heading_text_color          = '';
		$sub_heading_font_family         = '';
		$sub_heading_font_weight         = '';
		$product_title_text_color        = '';
		$product_title_font_family       = '';
		$product_title_font_weight       = '';
		$product_description_text_color  = '';
		$accept_offer_button_color       = '';
		$accept_offer_button_bg_color    = '';
		$accept_offer_button_font_family = '';
		$accept_offer_button_font_weight = '';
		$accept_offer_button_font_size   = '';
		$reject_offer_link_color         = '';
		
		// Prepare the CSS values for PRO options.
		if ( 'yes' === $is_design_setting_enabled ) {
			// Primary colors.
			$primary_color    = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-offer-primary-color' );
			$base_font_family = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-offer-heading-font-family' );

			// Column background colors.
			$left_section_bg_color  = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-instant-offer-left-side-bg-color' );
			$right_section_bg_color = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-instant-offer-right-side-bg-color' );

			// Advanced Option is enabled.
			$advance_options = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-advance-options-fields' );
			
			// Advanced Options.
			if ( 'yes' === $advance_options ) {
				// Main Heading.
				$heading_text_color  = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-heading-color' );
				$heading_font_family = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-heading-font-family' );
				$heading_font_weight = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-heading-font-weight' );
				// Sub Heading.
				$sub_heading_text_color  = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-sub-heading-color' );
				$sub_heading_font_family = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-sub-heading-font-family' );
				$sub_heading_font_weight = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-sub-heading-font-weight' );
				// Product Title.
				$product_title_text_color  = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-product-title-color' );
				$product_title_font_family = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-product-title-font-family' );
				$product_title_font_weight = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-product-title-font-weight' );
				// Product Descritption.
				$product_description_text_color = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-product-description-color' );
				// Offer Accept Button.
				$accept_offer_button_color       = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-accept-offer-button-color' );
				$accept_offer_button_bg_color    = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-accept-offer-button-bg-color' );
				$accept_offer_button_font_family = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-accept-offer-button-font-family' );
				$accept_offer_button_font_weight = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-accept-offer-button-font-weight' );
				$accept_offer_button_font_size   = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-accept-offer-button-font-size' );
				// Offer Reject Link.
				$reject_offer_link_color = wcf_pro()->options->get_offers_meta_value( $offer_step_id, 'wcf-reject-offer-link-color' );
			}
			
			$output .= 'body .wcf-instant-offer { ';
				// Primary colors.
				$output .= ! empty( $primary_color ) ? '--wcf-offer-primary-color: ' . $primary_color . ';' : '';
				$output .= ! empty( $base_font_family ) ? '--wcf-offer-primary-font-family: ' . $base_font_family . ';' : '';

				// Column background colors.
				$output .= ! empty( $left_section_bg_color ) ? '--wcf-instant-offer-left-side-bg-color: ' . $left_section_bg_color . ';' : '';
				$output .= ! empty( $right_section_bg_color ) ? '--wcf-instant-offer-right-side-bg-color: ' . $right_section_bg_color . ';' : '';

			if ( 'yes' === $advance_options ) {
				// Main Heading.
				$output .= ! empty( $heading_text_color ) ? '--wcf-heading-text-color: ' . $heading_text_color . ';' : '';
				$output .= ! empty( $heading_font_family ) ? '--wcf-heading-font-family: ' . $heading_font_family . ';' : '';
				$output .= ! empty( $heading_font_weight ) ? '--wcf-heading-font-weight: ' . $heading_font_weight . ';' : '';

				// Sub Heading.
				$output .= ! empty( $sub_heading_text_color ) ? '--wcf-sub-heading-color: ' . $sub_heading_text_color . ';' : '';
				$output .= ! empty( $sub_heading_font_family ) ? '--wcf-sub-heading-font-family: ' . $sub_heading_font_family . ';' : '';
				$output .= ! empty( $sub_heading_font_weight ) ? '--wcf-sub-heading-font-weight: ' . $sub_heading_font_weight . ';' : '';
				
				// Product Title.
				$output .= ! empty( $product_title_text_color ) ? '--wcf-product-title-color: ' . $product_title_text_color . ';' : '';
				$output .= ! empty( $product_title_font_family ) ? '--wcf-product-title-font-family: ' . $product_title_font_family . ';' : '';
				$output .= ! empty( $product_title_font_weight ) ? '--wcf-product-title-font-weight: ' . $product_title_font_weight . ';' : '';

				// Product Description.
				$output .= ! empty( $product_description_text_color ) ? '--wcf-product-description-color: ' . $product_description_text_color . ';' : '';

				// Offer Accept Button.
				$output .= ! empty( $accept_offer_button_color ) ? '--wcf-accept-offer-button-color: ' . $accept_offer_button_color . ';' : '';
				$output .= ! empty( $accept_offer_button_bg_color ) ? '--wcf-accept-offer-button-bg-color: ' . $accept_offer_button_bg_color . ';' : '';
				$output .= ! empty( $accept_offer_button_font_size ) ? '--wcf-accept-offer-button-font-size: ' . $accept_offer_button_font_size . 'px;' : '';
				$output .= ! empty( $accept_offer_button_font_family ) ? '--wcf-accept-offer-button-font-family: ' . $accept_offer_button_font_family . ';' : '';
				$output .= ! empty( $accept_offer_button_font_weight ) ? '--wcf-accept-offer-button-font-weight: ' . $accept_offer_button_font_weight . ';' : '';

				// Offer Reject Link.
				$output .= ! empty( $reject_offer_link_color ) ? '--wcf-reject-offer-link-color: ' . $reject_offer_link_color . ';' : '';
			}

			$output .= '}';
		}

		return $output;
	}

	/**
	 * Add a designed block message for an empty cart.
	 *
	 * This method is used to add a custom message or block when the cart is empty.
	 * The message or block design is typically intended to be displayed in place of
	 * the cart contents, providing a more visually appealing notification or call-to-action
	 * for the user to take further steps (e.g., continue shopping or view products).
	 *
	 * @since x.x.x
	 * @param int $offer_step_id The current step ID.
	 * @return string $output The HTML of empty cart block.
	 */
	public function render_no_product_selected_message( $offer_step_id ) {

		$edit_step_url = Cartflows_Pro_Helper::get_current_page_edit_url( 'products' );
		$step_type     = wcf()->utils->get_step_type( $offer_step_id );
		$message       = sprintf( 
			/* translators: %s: Current step type. */
			__( 'You haven\'t chosen an %s product yet. Select one from the step settings to continue!', 'cartflows-pro' ), 
			$step_type 
		);
		$heading     = __( 'No Upsell Product Selected.', 'cartflows-pro' );
		$button_text = sprintf( 
			/* translators: %s: Current step type. */
			__( 'Select %s Product', 'cartflows-pro' ),
			$step_type 
		);

		$output                      = '<div class="wcf-empty-cart-notice-block">';
			$output                 .= '<div class="wcf-empty-cart-message-container">';
				$output             .= '<div class="wcf-empty-cart-wrapper">';
					$output         .= '<div class="wcf-empty-cart-icon">';
						$output     .= '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="">';
							$output .= '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />';
						$output     .= '</svg>';
					$output         .= '</div>';
					$output         .= '<div class="wcf-empty-cart-content">';
						$output     .= '<h2 class="wcf-empty-cart-heading">' . esc_html( apply_filters( 'cartflows_checkout_empty_cart_heading', $heading ) ) . '</h2>';
						$output     .= '<p class="wcf-empty-cart-message">' . esc_html( apply_filters( 'cartflows_checkout_empty_cart_message', $message ) ) . '</p>';
						$output     .= ! empty( $edit_step_url ) ? '<a href="' . esc_url( $edit_step_url ) . '" class="wcf-empty-cart-button">' . esc_html( apply_filters( 'cartflows_checkout_empty_cart_button_text', $button_text ) ) . '</a>' : '';
					$output         .= '</div>';
				$output             .= '</div>';
			$output                 .= '</div>';
		$output                     .= '</div>';

		return $output;
	}

	/**
	 * Modify the string for availability text.
	 *
	 * This method is used to modify the string for availability text.
	 * It checks if the availability is not empty and if the availability class and availability are not empty.
	 * If all conditions are met, it modifies the HTML to display the availability.
	 *
	 * @since x.x.x
	 * @param string     $html The original HTML.
	 * @param WC_Product $product The product object.
	 * @return string $html The modified HTML.
	 */
	public function modify_string_for_availibility_text( $html, $product ) {
		
		$flow_id = wcf()->utils->is_step_post_type() ? wcf()->utils->get_flow_id() : 0;

		if ( empty( $flow_id ) || ! class_exists( 'Cartflows_Helper' ) || ! Cartflows_Helper::is_instant_layout_enabled( (int) $flow_id ) ) {
			return $html;
		}
		
		$availability = $product->get_availability();

		if ( ! empty( $availability ) && ! empty( $availability['class'] && ! empty( $availability['availability'] ) ) ) {

			$html = '<p class="stock ' . $availability['class'] . '">Availability: ' . $availability['availability'] . '</p>';
		}

		return $html;
	}
}

/**
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Pro_Base_Offer_Markup::get_instance();

<?php
/**
 * Cartflows Gateways.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Cartflows_Pro_Gateways.
 */
class Cartflows_Pro_Gateways {

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	public $gateway_obj = array();

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
	 * Constructor
	 */
	public function __construct() {

		add_action( 'wp_loaded', array( $this, 'load_required_integrations' ), 20 );

		add_action( 'wp_ajax_nopriv_cartflows_front_create_express_checkout_token', array( $this, 'generate_express_checkout_token' ), 10 );
		add_action( 'wp_ajax_cartflows_front_create_express_checkout_token', array( $this, 'generate_express_checkout_token' ), 10 );

		add_action( 'wp_ajax_nopriv_cartflows_front_create_ppec_paypal_checkout_token', array( $this, 'generate_ppec_paypal_checkout_token' ), 10 );
		add_action( 'wp_ajax_cartflows_front_create_ppec_paypal_checkout_token', array( $this, 'generate_ppec_paypal_checkout_token' ), 10 );

		/**
		 * Paypal Standard API calls response and process billing agreement creation
		 */
		add_action( 'woocommerce_api_cartflows_paypal', array( $this, 'maybe_handle_paypal_api_call' ) );

		/**
		 * Paypal Express API calls response and process billing agreement creation
		 */
		add_action( 'woocommerce_api_cartflows_ppec_paypal', array( $this, 'maybe_handle_ppec_paypal_api_call' ) );

		$this->load_payment_gateway_notices();

		/**
		 *  Add actions and filters for Angelleye.
		 */
		do_action( 'cartflows_add_offer_payment_gateway_actions' );

	}

	/**
	 * Function to register any notices which are related to payment gateways.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function load_payment_gateway_notices() {
		add_filter( 'cartflows_admin_notices', array( $this, 'add_payment_gateways_not_supported_notice' ) );
	}

	/**
	 * Load required gateways.
	 *
	 * @since 1.0.0
	 * @return array.
	 */
	public function load_required_integrations() {

		$gateways = $this->get_supported_gateways();

		/*
		@TODO Get the active payment gateways from DB or from any other function as below function is making issue with Mollie Bank Transfer Gateway.
		$available_payment_methods = array_keys( WC()->payment_gateways->get_available_payment_gateways() );
		*/

		if ( is_array( $gateways ) ) {

			foreach ( $gateways as $key => $gateway ) {
				/** Condition commented out for loading gateways files.
				* if ( in_array( $key, $available_payment_methods, true ) ) {
				* $this->load_gateway( $key );
				*/
				$this->load_gateway( $key );
			}
		}

		return $gateways;
	}

	/**
	 * Load Gateway.
	 *
	 * @param string $type gateway type.
	 * @since 1.0.0
	 * @return array.
	 */
	public function load_gateway( $type ) {

		$gateways = $this->get_supported_gateways();

		if ( isset( $gateways[ $type ] ) ) {

			$temp_gateway = $gateways[ $type ];
			$gateway_path = isset( $temp_gateway['path'] ) ? $temp_gateway['path'] : CARTFLOWS_PRO_DIR . 'modules/gateways/class-cartflows-pro-gateway-' . $temp_gateway['file'];
			if ( ! file_exists( $gateway_path ) ) {
				return false;
			}
			include_once $gateway_path;
			$class_name = $temp_gateway['class'];

			$this->gateway_obj[ $class_name ] = call_user_func( array( $class_name, 'get_instance' ) );

			return $this->gateway_obj[ $class_name ];
		}

		return false;
	}

	/**
	 * Generates express checkout token
	 *
	 * @since 1.0.0
	 * @return void.
	 */
	public function generate_express_checkout_token() {
		$this->load_gateway( 'paypal' )->generate_express_checkout_token();
	}

	/**
	 * Generates express checkout token
	 *
	 * @since 1.0.0
	 * @return void.
	 */
	public function generate_ppec_paypal_checkout_token() {
		$this->load_gateway( 'ppec_paypal' )->generate_express_checkout_token();
	}

	/**
	 * Get Supported Gateways.
	 *
	 * @since 1.0.0
	 * @return array $supported_gateways Updated Supported payment gateways.
	 */
	public function get_supported_gateways() {

		// Default square version that we require for the CartFlows.
		$wc_square_version = '2.9.1';

		if ( function_exists( 'wc_square' ) ) {
			$wc_square_version = wc_square()->get_version();
		}

		// Return true if greater than 3.0.0 else retur false.
		$is_latest_square = version_compare( $wc_square_version, '3.0.0', '>=' );

		$supported_gateways = array(
			'bacs'                          => array(
				'file'  => 'bacs.php',
				'class' => 'Cartflows_Pro_Gateway_Bacs',
			),
			'cod'                           => array(
				'file'  => 'cod.php',
				'class' => 'Cartflows_Pro_Gateway_Cod',
			),
			'stripe'                        => array(
				'file'  => 'stripe.php',
				'class' => 'Cartflows_Pro_Gateway_Stripe',
			),
			'cpsw_stripe'                   => array(
				'file'  => 'cpsw-stripe.php',
				'class' => 'Cartflows_Pro_Gateway_Cpsw_Stripe',
			),
			'paypal'                        => array(
				'file'  => 'paypal-standard.php',
				'class' => 'Cartflows_Pro_Gateway_Paypal_Standard',
			),
			'ppec_paypal'                   => array(
				'file'  => 'paypal-express.php',
				'class' => 'Cartflows_Pro_Gateway_Paypal_Express',
			),
			'ppcp-gateway'                  => array(
				'file'  => 'paypal-payments.php',
				'class' => 'Cartflows_Pro_Gateway_Paypal_Payments',
			),
			'authorize_net_cim_credit_card' => array(
				'file'  => 'authorize-net.php',
				'class' => 'Cartflows_Pro_Gateway_Authorize_Net',
			),
			'mollie_wc_gateway_creditcard'  => array(
				'file'  => 'mollie-credit-card.php',
				'class' => 'Cartflows_Pro_Gateway_Mollie_Credit_Card',
			),
			'mollie_wc_gateway_ideal'       => array(
				'file'  => 'mollie-ideal.php',
				'class' => 'Cartflows_Pro_Gateway_Mollie_Ideal',
			),
			'square_credit_card'            => array(
				'file'  => $is_latest_square ? 'square.php' : 'square-old.php',
				'class' => $is_latest_square ? 'Cartflows_Pro_Gateway_Square' : 'Cartflows_Pro_Gateway_Square_old',
			),
			'woocommerce_payments'          => array(
				'file'  => 'woocommerce-payments.php',
				'class' => 'Cartflows_Pro_Gateway_Woocommerce_Payments',
			),
			'cppw_paypal'                   => array(
				'file'  => 'cppw-paypal.php',
				'class' => 'Cartflows_Pro_Gateway_Cppw_Paypal',
			),
			'cpsw_stripe_element'           => array(
				'file'  => 'cpsw-stripe-element.php',
				'class' => 'Cartflows_Pro_Gateway_Cpsw_Stripe_Element',
			),
		);

		return apply_filters( 'cartflows_offer_supported_payment_gateways', $supported_gateways );

	}

	/**
	 * Handles paypal API call
	 *
	 * @since 1.0.0
	 * @return void.
	 */
	public function maybe_handle_paypal_api_call() {

		$this->load_gateway( 'paypal' )->create_billing_agreement();
		$this->load_gateway( 'paypal' )->process_api_calls();
	}

	/**
	 * Handles ppec_paypal API call
	 *
	 * @since 1.0.0
	 * @return void.
	 */
	public function maybe_handle_ppec_paypal_api_call() {

		$this->load_gateway( 'ppec_paypal' )->create_billing_agreement();
		$this->load_gateway( 'ppec_paypal' )->process_api_calls();
	}

	/**
	 * Add Payment Gateway not supported notices.
	 *
	 * @since x.x.x
	 * @param array $notices Notices array.
	 * @return array $notices The modified array of notices for the non-supported gateways.
	 */
	public function add_payment_gateways_not_supported_notice( $notices = array() ) {
		$payment_gateway_notice = $this->prepare_gateway_not_supported_notices();

		if ( ! empty( $payment_gateway_notice ) ) {
			$notices[] = $payment_gateway_notice;
		}

		return $notices;
	}

	/**
	 * Prepare the gateway not supported notices to display in-plugin notice.
	 *
	 * @since x.x.x
	 *
	 * @return string $notice The modified and prepared notice for the non supported gateways.
	 */
	public function prepare_gateway_not_supported_notices() {

		$notice                 = '';
		$current_flow_steps     = array();
		$available_gateways     = array();
		$not_supported_gateways = array();

		// Get current funnel ID.
		$flow_id = isset( $_GET['flow_id'] ) ? intval( $_GET['flow_id'] ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Return if flow ID is not set.
		if ( empty( $flow_id ) ) {
			return $notice;
		}

		// Get steps of currently selected/opened flow.
		$current_flow_steps = wcf()->flow->get_steps( $flow_id );

		$cartflows_plugin_type = defined( 'CARTFLOWS_PRO_PLUGIN_TYPE' ) ? CARTFLOWS_PRO_PLUGIN_TYPE : 'free';

		if (
			is_plugin_active( 'woocommerce/woocommerce.php' ) && 'plus' === $cartflows_plugin_type &&
			! empty( $current_flow_steps )
		) {
			$show_notice = false;

			// Display the notice only for the offer steps such as upsell/downsell.
			foreach ( $current_flow_steps as $step ) {
				if ( 'upsell' === $step['type'] || 'downsell' === $step['type'] ) {
					$show_notice = true;
				}
			}

			// Get supported gateways of CartFlows.
			$supported_gateways = $this->get_supported_gateways();

			// Get the available gateways installed and activated on the website.
			$woo_available_gateways = WC()->payment_gateways->get_available_payment_gateways();

			if ( ! empty( $woo_available_gateways ) && is_array( $woo_available_gateways ) ) {
				foreach ( $woo_available_gateways as  $key => $value ) {
					$available_gateways[ $key ]['method_title'] = ! empty( $value->method_title ) ? $value->method_title : '';
				}
			}

			if ( $show_notice && $supported_gateways && ! empty( $available_gateways ) && is_array( $available_gateways ) ) {

				foreach ( $available_gateways as $gateway => $gateway_details ) {
					if ( ! array_key_exists( $gateway, $supported_gateways ) ) {
						$not_supported_gateways[] = $gateway_details['method_title'];
					}
				}

				if ( ! empty( $not_supported_gateways ) && is_array( $not_supported_gateways ) ) {
					$not_supported_gateways = implode( ', ', $not_supported_gateways );
					$notice                 = '<div class="wcf-payment-gateway-notice--text"><p class="text-sm text-yellow-700">' . wp_kses_post(
						sprintf(
						/* translators: %1$s: payment gateway names, %2$s: link start, %3$s: link end */
							__( 'CartFlows Upsell/Downsell offer does not support the %1$s payment gateway. Please find the supported payment gateways %2$shere%3$s.', 'cartflows-pro' ),
							'<span class="capitalize">' . esc_html( $not_supported_gateways ) . '</span>',
							'<span class="font-medium"><a href="https://cartflows.com/docs/supported-payment-gateways-by-cartflows/" class="text-yellow-700 underline" target="_blank" rel="noreferrer">',
							'</a></span>'
						)
					);
				}
			}
		}

		// Return the notice string.
		return $notice;

	}
}

/**
 *  Prepare if class 'Cartflows_Pro_Gateways' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Pro_Gateways::get_instance();

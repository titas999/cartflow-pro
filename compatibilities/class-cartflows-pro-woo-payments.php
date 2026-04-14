<?php
/**
 * WooCommerce Payments.
 *
 * Compatibility of Plugin: WooCommerce Payments
 * Plugin URI: https://woocommerce.com/
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Cartflows_Pro_Utils.
 */
class Cartflows_Pro_Woo_Payments {
	/**
	 * Single instance of the class.
	 *
	 * @var Cartflows_Pro_Woo_Payments
	 */
	protected static $instance;

	/**
	 * The flat to save weather the multicurrency feature is enabled.
	 *
	 * @var bool
	 */
	public static $is_multicurrency_enabled;

	/**
	 * Main Cartflows_Pro_Woo_Payments Instance.
	 *
	 * Ensures only one instance of Cartflows_Pro_Woo_Payments is loaded or can be loaded.
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {

		self::$is_multicurrency_enabled = class_exists( 'WC_Payments_Features' ) && WC_Payments_Features::is_customer_multi_currency_enabled();

		add_filter( 'cartflows_filter_display_price', array( $this, 'wcf_modify_product_price' ), 10, 3 );
		add_filter( 'cartflows_filter_currency_code', array( $this, 'display_converted_price_currency_code' ), 10, 2 );
		add_filter( 'cartflows_checkout_trigger_update_order_review', array( $this, 'trigger_update_order_review' ), 10, 1 );
	}

	/**
	 * Price Converter
	 *
	 * @param int    $product_price product price.
	 * @param int    $product_id current product ID.
	 * @param string $context The context of the action.
	 *
	 * @return float
	 */
	public function wcf_modify_product_price( $product_price, $product_id, $context ) {

		if ( function_exists( 'WC_Payments_Multi_Currency' ) && self::$is_multicurrency_enabled && ! wcpay_multi_currency_onboarding_check() ) {
			$product_price = WC_Payments_Multi_Currency()->get_price( $product_price, 'product' );
		}

		return $product_price;

	}

	/**
	 * Convert The currency code according to the selected currency on the page.
	 *
	 * @param string $currency_code The currency code.
	 * @param string $context The context of the action.
	 *
	 * @return string   $currency_code The converted currency code.
	 */
	public function display_converted_price_currency_code( $currency_code, $context ) {

		if ( self::$is_multicurrency_enabled && function_exists( 'WC_Payments_Multi_Currency' ) ) {
			$selected_currency = WC_Payments_Multi_Currency()->get_selected_currency();
			$currency_code     = $selected_currency->get_code();
		}

		return $currency_code;
	}

	/**
	 * Trigger update order review.
	 *
	 * @param bool $bool Trigger update order review.
	 *
	 * @return boolean $bool
	 **/
	public function trigger_update_order_review( $bool ) {
		if ( class_exists( 'WC_Payments' ) ) {
			$bool = true;
		}
		return $bool;
	}

}

/**
 *  Prepare if class 'Cartflows_Pro_Woo_Payments' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Pro_Woo_Payments::get_instance();


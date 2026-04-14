<?php
/**
 * Cpsw_Stripe Gateway helper functions.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Cartflows_Pro_Paypal_Gateway_Helper .
 */
class Cartflows_Pro_Cpsw_Stripe_Gateway_Helper extends Cartflows_Pro_Gateway {
	
	/**
	 * Tokenize to save source of payment if required
	 *
	 * @param bool $save_source force save source.
	 * @return bool
	 */
	public function tokenize_if_required( $save_source ) {

		wcf()->logger->log( 'Started: ' . __CLASS__ . '::' . __FUNCTION__ );

		$checkout_id        = wcf()->utils->get_checkout_id_from_post_data();
		$flow_id            = wcf()->utils->get_flow_id_from_post_data();
		$is_offer_supported = true;
		$payment_method     = isset( $_POST['payment_method'] ) ? sanitize_text_field( $_POST['payment_method'] ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'cpsw_stripe_element' === $payment_method ) {
			$selected_payment_type = isset( $_POST['selected_payment_type'] ) ? sanitize_text_field( $_POST['selected_payment_type'] ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Check if selected payment type is supported for offers, using an array for future extensibility.
			$is_offer_supported = $selected_payment_type && in_array( $selected_payment_type, array( 'card' ) );
		}

		if ( $checkout_id && $flow_id && $is_offer_supported && wcf_pro()->flow->is_upsell_exist_in_flow( $flow_id, $checkout_id ) ) {
			$save_source = true;
			wcf()->logger->log( 'Force save source enabled' );
		}

		wcf()->logger->log( 'Ended : ' . __CLASS__ . '::' . __FUNCTION__ );

		return $save_source;
	}

	/**
	 * Redirection to order received URL.
	 *
	 * @param array    $url response data.
	 * @param WC_Order $order The WooCommerce order object.
	 */
	public function redirect_using_wc_function( $url, $order ) {

		wcf()->logger->log( 'Started: ' . __CLASS__ . '::' . __FUNCTION__ );

		if ( did_action( 'cartflows_order_started' ) ) {

			$url = $order->get_checkout_order_received_url();
		}

		return $url;
	}

	/**
	 * Setup the Payment data for Stripe's Automatic Subscription.
	 *
	 * @param object $subscription An instance of a subscription object.
	 * @param object $order Object of order.
	 * @param array  $offer_product array of offer product.
	 */
	public function add_subscription_payment_meta( $subscription, $order, $offer_product ) {

		if ( 'cpsw_stripe' === $order->get_payment_method() || 'cpsw_stripe_element' === $order->get_payment_method() ) {

			$subscription->update_meta_data( '_cpsw_source_id', $order->get_meta( '_cpsw_source_id', true ) );
			$subscription->update_meta_data( '_cpsw_customer_id', $order->get_meta( '_cpsw_customer_id', true ) );
			$subscription->save();
		}
	}

	/**
	 * Allow scripts on offer pages.
	 *
	 * @param bool $is_exclude is allowed scripts.
	 */
	public function allow_cpsw_scripts_on_offer_pages( $is_exclude ) {

		global $post;

		if ( $post && wcf()->utils->check_is_offer_page( $post->ID ) ) {
			$is_exclude = false;
		}

		return $is_exclude;
	}

}

<?php
/**
 * Cartflows Pro tracking.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Cartflows_Pro_Tracking.
 */
class Cartflows_Pro_Tracking {

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	/**
	 * Member Variable
	 *
	 * @var fb_pixel_settings
	 */
	private static $fb_pixel_settings;

	/**
	 * Member Variable
	 *
	 * @var tiktok_pixel_settings
	 */
	private static $tiktok_pixel_settings;

	/**
	 * Member Variable
	 *
	 * @var ga_settings
	 */
	private static $ga_settings;

	/**
	 * Member Variable
	 *
	 * @var array
	 */
	private static $pinterest_tag_settings;

	/**
	 * Member Variable
	 *
	 * @var array
	 */
	private static $gads_settings;

	/**
	 * Member Variable
	 *
	 * @var array
	 */
	private static $snapchat_pixel_settings;

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

		self::$fb_pixel_settings = Cartflows_Helper::get_facebook_settings();
		self::$ga_settings       = Cartflows_Helper::get_google_analytics_settings();

		if ( class_exists( 'Cartflows_Helper' ) && method_exists( 'Cartflows_Helper', 'get_tiktok_settings' ) ) {
			self::$tiktok_pixel_settings = Cartflows_Helper::get_tiktok_settings();
		}

		if ( class_exists( 'Cartflows_Helper' ) && method_exists( 'Cartflows_Helper', 'get_google_ads_settings' ) ) {
			self::$gads_settings = Cartflows_Helper::get_google_ads_settings();
		}

		if ( class_exists( 'Cartflows_Helper' ) && method_exists( 'Cartflows_Helper', 'get_snapchat_settings' ) ) {
			self::$snapchat_pixel_settings = Cartflows_Helper::get_snapchat_settings();
		}

		self::$ga_settings = Cartflows_Helper::get_google_analytics_settings();
		if ( class_exists( 'Cartflows_Helper' ) && method_exists( 'Cartflows_Helper', 'get_pinterest_settings' ) ) {
			self::$pinterest_tag_settings = Cartflows_Helper::get_pinterest_settings();
		}

		if ( 'enable' === self::$fb_pixel_settings['facebook_pixel_tracking'] ) {
			/* Facebook Pixel */
			add_action( 'wcf_order_bump_item_added', array( $this, 'trigger_fb_event' ) );
			add_action( 'wcf_after_quantity_update', array( $this, 'trigger_fb_event' ) );
			add_action( 'wcf_after_force_all_selection', array( $this, 'trigger_fb_event' ) );
			add_action( 'wcf_after_multiple_selection', array( $this, 'trigger_fb_event' ) );
			add_action( 'wcf_after_single_selection', array( $this, 'trigger_fb_event' ) );

			add_action( 'cartflows_offer_product_processed', array( $this, 'save_offer_data_for_fb' ), 10, 3 );
			add_filter( 'cartflows_view_content_offer', array( $this, 'trigger_offer_viewcontent_event' ), 10, 2 );
			add_action( 'cartflows_facebook_pixel_events', array( $this, 'trigger_offer_purchase_event' ), 10 );

			add_action( 'wcf_order_bump_item_removed', array( $this, 'update_cart_data_for_fb_event' ) );
		}

		if ( isset( self::$tiktok_pixel_settings['tiktok_pixel_tracking'] ) && 'enable' === self::$tiktok_pixel_settings['tiktok_pixel_tracking'] ) {
			/* Tiktok Pixel */
			add_action( 'wcf_order_bump_item_added', array( $this, 'trigger_tiktok_event' ) );
			add_action( 'wcf_after_quantity_update', array( $this, 'trigger_tiktok_event' ) );
			add_action( 'wcf_after_force_all_selection', array( $this, 'trigger_tiktok_event' ) );
			add_action( 'wcf_after_multiple_selection', array( $this, 'trigger_tiktok_event' ) );
			add_action( 'wcf_after_single_selection', array( $this, 'trigger_tiktok_event' ) );

			add_action( 'cartflows_offer_product_processed', array( $this, 'save_offer_data_for_tiktok' ), 10, 3 );
			add_filter( 'cartflows_tiktok_view_content_offer', array( $this, 'trigger_offer_viewcontent_event_for_tiktok' ), 10, 2 );
			add_action( 'cartflows_tiktok_pixel_events', array( $this, 'trigger_offer_purchase_event_for_tiktok' ), 10 );

			add_action( 'wcf_order_bump_item_removed', array( $this, 'update_cart_data_for_tiktok_event' ) );
		}

		if ( 'enable' === self::$ga_settings['enable_google_analytics'] ) {
			/* Google analyics add to cart */
			add_action( 'wcf_order_bump_item_added', array( $this, 'trigger_ga_add_to_cart_event' ) );
			add_action( 'wcf_after_quantity_update', array( $this, 'trigger_ga_add_to_cart_event' ) );
			add_action( 'wcf_after_force_all_selection', array( $this, 'trigger_ga_add_to_cart_event' ) );
			add_action( 'wcf_after_multiple_selection', array( $this, 'trigger_ga_add_to_cart_event' ) );
			add_action( 'wcf_after_single_selection', array( $this, 'trigger_ga_add_to_cart_event' ) );

			/* Google analyics remove from cart */
			add_action( 'wcf_order_bump_item_removed', array( $this, 'trigger_ga_remove_from_cart_event' ) );

			add_action( 'cartflows_offer_product_processed', array( $this, 'save_offer_data_for_ga' ), 10, 3 );
			add_action( 'cartflows_google_analytics_events', array( $this, 'trigger_offer_purchase_event_for_ga' ), 10 );
		}

		if ( isset( self::$pinterest_tag_settings['pinterest_tag_tracking'] ) && 'enable' === self::$pinterest_tag_settings['pinterest_tag_tracking'] ) {
			/* Pinterest Tag */
			add_action( 'wcf_order_bump_item_added', array( $this, 'trigger_pinterest_event' ) );
			add_action( 'wcf_after_quantity_update', array( $this, 'trigger_pinterest_event' ) );
			add_action( 'wcf_after_force_all_selection', array( $this, 'trigger_pinterest_event' ) );
			add_action( 'wcf_after_multiple_selection', array( $this, 'trigger_pinterest_event' ) );
			add_action( 'wcf_after_single_selection', array( $this, 'trigger_pinterest_event' ) );

			add_action( 'cartflows_offer_product_processed', array( $this, 'save_offer_data_for_pinterest' ), 10, 3 );
			add_action( 'cartflows_pinterest_tag_events', array( $this, 'trigger_offer_purchase_event_for_pinterest' ), 10 );

			add_action( 'wcf_order_bump_item_removed', array( $this, 'update_cart_data_for_pinterest_event' ) );
		}

		if ( isset( self::$gads_settings['google_ads_tracking'] ) && 'enable' === self::$gads_settings['google_ads_tracking'] ) {
			/* Google Ads */
			add_action( 'wcf_order_bump_item_added', array( $this, 'trigger_gads_add_to_cart_event' ) );
			add_action( 'wcf_after_quantity_update', array( $this, 'trigger_gads_add_to_cart_event' ) );
			add_action( 'wcf_after_force_all_selection', array( $this, 'trigger_gads_add_to_cart_event' ) );
			add_action( 'wcf_after_multiple_selection', array( $this, 'trigger_gads_add_to_cart_event' ) );
			add_action( 'wcf_after_single_selection', array( $this, 'trigger_gads_add_to_cart_event' ) );

			add_action( 'cartflows_offer_product_processed', array( $this, 'save_offer_data_for_gads' ), 10, 3 );
			add_action( 'cartflows_google_ads_events', array( $this, 'trigger_offer_purchase_event_for_gads' ), 10 );

			add_action( 'wcf_order_bump_item_removed', array( $this, 'update_cart_data_for_gads_event' ) );
		}

		if ( isset( self::$snapchat_pixel_settings['snapchat_pixel_tracking'] ) && 'enable' === self::$snapchat_pixel_settings['snapchat_pixel_tracking'] ) {
			/* snapchat Pixel */
			add_action( 'wcf_order_bump_item_added', array( $this, 'trigger_snapchat_event' ) );
			add_action( 'wcf_after_quantity_update', array( $this, 'trigger_snapchat_event' ) );
			add_action( 'wcf_after_force_all_selection', array( $this, 'trigger_snapchat_event' ) );
			add_action( 'wcf_after_multiple_selection', array( $this, 'trigger_snapchat_event' ) );
			add_action( 'wcf_after_single_selection', array( $this, 'trigger_snapchat_event' ) );

			add_action( 'cartflows_offer_product_processed', array( $this, 'save_offer_data_for_snapchat' ), 10, 3 );
			add_filter( 'cartflows_snapchat_view_content_offer', array( $this, 'trigger_offer_viewcontent_event_for_snapchat' ), 10, 2 );
			add_action( 'cartflows_snapchat_pixel_events', array( $this, 'trigger_offer_purchase_event_for_snapchat' ), 10 );

			add_action( 'wcf_order_bump_item_removed', array( $this, 'update_cart_data_for_snapchat_event' ) );
		}
	}

	/**
	 * Update the cart data for add payment info event when order bump product is removed from cart.
	 */
	public function update_cart_data_for_fb_event() {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) {

				if ( 'enable' === self::$fb_pixel_settings['facebook_pixel_add_payment_info'] ) {
					$data['fb_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_fb_response( 'add_payment_info' ) );
				}

				return $data;
			}
		);

	}

	/**
	 * Update the cart data for add payment info event when order bump product is removed from cart.
	 *
	 * @return void
	 */
	public function update_cart_data_for_tiktok_event() {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) {

				if ( 'enable' === self::$tiktok_pixel_settings['enable_tiktok_add_payment_info'] ) {
					$data['tiktok_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_tiktok_response( 'add_payment_info' ) );
				}

				return $data;
			}
		);

	}

	/**
	 * Update the cart data for add payment info event when order bump product is removed from cart.
	 *
	 * @return void
	 */
	public function update_cart_data_for_pinterest_event() {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) {

				if ( 'enable' === self::$pinterest_tag_settings['enable_pinterest_add_payment_info'] ) {
					$data['pinterest_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_pinterest_response() );
				}

				return $data;
			}
		);

	}

	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 */
	public function trigger_fb_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['added_to_cart_data'] = $this->prepare_fb_response( $product_id );

				if ( 'enable' === self::$fb_pixel_settings['facebook_pixel_add_payment_info'] ) {
					$data['fb_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_fb_response( 'add_payment_info' ) );
				}

				return $data;
			}
		);

	}

	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 * @return void.
	 */
	public function trigger_tiktok_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['tiktok_added_to_cart_data'] = $this->prepare_tiktok_response( $product_id );

				if ( 'enable' === self::$tiktok_pixel_settings['enable_tiktok_add_payment_info'] ) {
					$data['tiktok_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_tiktok_response( 'add_payment_info' ) );
				}
				return $data;
			}
		);

	}

	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 * @return void.
	 */
	public function trigger_snapchat_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['snapchat_added_to_cart_data'] = $this->prepare_snapchat_response( $product_id );
				return $data;
			}
		);

	}
	
	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 * @return void.
	 */
	public function trigger_pinterest_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['pinterest_added_to_cart_data'] = $this->prepare_pinterest_response( $product_id );

				if ( 'enable' === self::$pinterest_tag_settings['enable_pinterest_add_payment_info'] ) {
					$data['pinterest_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_pinterest_response() );
				}
				return $data;
			}
		);

	}

	/**
	 * Save the offer details in transient to use for facebook pixel.
	 *
	 * @param object $parent_order parent order id.
	 * @param array  $offer_data offer product data.
	 * @param object $child_order child order.
	 */
	public function save_offer_data_for_fb( $parent_order, $offer_data, $child_order = null ) {
		$order_id = null;
		$user_key = WC()->session->get_customer_id();

		if ( $child_order ) {
			$order_id = $child_order->get_id();
		} else {
			$order_id = $parent_order->get_id();
		}

		$data = array(
			'order_id'      => $order_id,
			'offer_product' => $offer_data,
		);

		set_transient( 'wcf-offer-details-for-fbp-' . $user_key, $data );
	}

	/**
	 * Save the offer details in transient to use for tiktok pixel.
	 *
	 * @param object $parent_order parent order id.
	 * @param array  $offer_data offer product data.
	 * @param object $child_order child order.
	 * @return void.
	 */
	public function save_offer_data_for_tiktok( $parent_order, $offer_data, $child_order = null ) {
		$order_id = null;
		$user_key = WC()->session->get_customer_id();

		if ( $child_order ) {
			$order_id = $child_order->get_id();
		} else {
			$order_id = $parent_order->get_id();
		}

		$data = array(
			'order_id'      => $order_id,
			'offer_product' => $offer_data,
		);

		set_transient( 'wcf-offer-details-for-tiktok-' . $user_key, $data );
	}

	/**
	 * Save the offer details in transient to use for pinterest tag.
	 *
	 * @param object $parent_order parent order id.
	 * @param array  $offer_data offer product data.
	 * @param object $child_order child order.
	 * @return void.
	 */
	public function save_offer_data_for_pinterest( $parent_order, $offer_data, $child_order = null ) {
		$order_id = null;
		$user_key = WC()->session->get_customer_id();

		if ( $child_order ) {
			$order_id = $child_order->get_id();
		} else {
			$order_id = $parent_order->get_id();
		}

		$data = array(
			'order_id'      => $order_id,
			'offer_product' => $offer_data,
		);

		set_transient( 'wcf-offer-details-for-pinterest-' . $user_key, $data );
	}

	/**
	 * Save the offer details in transient to use for Google Analytics.
	 *
	 * @param object $parent_order parent order id.
	 * @param array  $offer_data offer product data.
	 * @param object $child_order child order.
	 */
	public function save_offer_data_for_ga( $parent_order, $offer_data, $child_order = null ) {

		$order_id = null;
		$user_key = WC()->session->get_customer_id();

		if ( $child_order ) {
			$order_id = $child_order->get_id();
		} else {
			$order_id = $parent_order->get_id();
		}

		$data = array(
			'order_id'      => $order_id,
			'offer_product' => $offer_data,
		);

		set_transient( 'wcf-offer-details-for-ga-' . $user_key, $data );
	}

	/**
	 * Save the offer details in transient to use for Google Analytics.
	 *
	 * @since x.x.x
	 * @param object $parent_order parent order id.
	 * @param array  $offer_data offer product data.
	 * @param object $child_order child order.
	 * @return void.
	 */
	public function save_offer_data_for_gads( $parent_order, $offer_data, $child_order = null ) {

		$order_id = null;
		$user_key = WC()->session->get_customer_id();

		if ( $child_order ) {
			$order_id = $child_order->get_id();
		} else {
			$order_id = $parent_order->get_id();
		}

		$data = array(
			'order_id'      => $order_id,
			'offer_product' => $offer_data,
		);

		set_transient( 'wcf-offer-details-for-gads-' . $user_key, $data );
	}

	/**
	 * Save the offer details in transient to use for snapchat pixel.
	 *
	 * @param object $parent_order parent order id.
	 * @param array  $offer_data offer product data.
	 * @param object $child_order child order.
	 * @return void.
	 */
	public function save_offer_data_for_snapchat( $parent_order, $offer_data, $child_order = null ) {
		$order_id = null;
		$user_key = WC()->session->get_customer_id();

		if ( $child_order ) {
			$order_id = $child_order->get_id();
		} else {
			$order_id = $parent_order->get_id();
		}

		$data = array(
			'order_id'      => $order_id,
			'offer_product' => $offer_data,
		);

		set_transient( 'wcf-offer-details-for-snapchat-' . $user_key, $data );
	}

	/**
	 * Trigger the purchase event for the upsell/downsell offer.
	 */
	public function trigger_offer_purchase_event() {

		if ( isset( $_GET['wcf-order'] ) && 'enable' === self::$fb_pixel_settings['facebook_pixel_purchase_complete'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$order_id = intval( $_GET['wcf-order'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$user_key = WC()->session->get_customer_id();

			$offer_data = get_transient( 'wcf-offer-details-for-fbp-' . $user_key );

			if ( empty( $offer_data ) ) {
				return;
			}

			$purchase_details = $this->prepare_offer_purchase_data_fb_response( $order_id, $offer_data );

			delete_transient( 'wcf-offer-details-for-fbp-' . $user_key );

			if ( ! empty( $purchase_details ) ) {

				$purchase_details = wp_json_encode( $purchase_details );
				$event_script     = "
					<script type='text/javascript'>
						fbq( 'track', 'Purchase', $purchase_details );
					</script>";

				// Reason for PHPCS ignore: Printing Google Analytics script. No input taken from the user except GA ID.
				echo html_entity_decode( $event_script ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Trigger the tiktok purchase event for the upsell/downsell offer.
	 *
	 * @return void.
	 */
	public function trigger_offer_purchase_event_for_tiktok() {

		if ( isset( $_GET['wcf-order'] ) && 'enable' === self::$tiktok_pixel_settings['enable_tiktok_purchase_event'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$order_id = intval( $_GET['wcf-order'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$user_key = WC()->session->get_customer_id();

			$offer_data   = get_transient( 'wcf-offer-details-for-tiktok-' . $user_key );
			$event_script = '';

			if ( empty( $offer_data ) ) {
				return;
			}

			$purchase_details = $this->prepare_offer_purchase_data_tiktok_response( $order_id, $offer_data );
			$cart_details     = $this->prepare_offer_accepted_data_tiktok_response( $offer_data );

			delete_transient( 'wcf-offer-details-for-tiktok-' . $user_key );

			// Apply the filter to check if the 'AddToCart' tracking should be triggered for offer steps.
			$enable_add_to_cart_tracking = apply_filters( 'cartflows_pro_enable_addtocart_for_offer_steps', true, $order_id );

			if ( $enable_add_to_cart_tracking && ! empty( $cart_details ) ) {

				$cart_details  = wp_json_encode( $cart_details );
				$event_script .= "
					<script type='text/javascript'>
						setTimeout(function () {
							ttq.track( 'AddToCart', $cart_details );
						}, 1300);
					</script>";
			}

			if ( ! empty( $purchase_details ) ) {

				$purchase_details = wp_json_encode( $purchase_details );
				$event_script    .= "
					<script type='text/javascript'>
						ttq.track( 'CompletePayment', $purchase_details );
					</script>";
			}

			echo html_entity_decode( $event_script ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Trigger the pinterest checkout event for the upsell/downsell offer.
	 *
	 * @return void.
	 */
	public function trigger_offer_purchase_event_for_pinterest() {

		if ( isset( $_GET['wcf-order'] ) && 'enable' === self::$pinterest_tag_settings['enable_pinterest_purchase_event'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$order_id = intval( $_GET['wcf-order'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$user_key = WC()->session->get_customer_id();

			$offer_data   = get_transient( 'wcf-offer-details-for-pinterest-' . $user_key );
			$event_script = '';

			if ( empty( $offer_data ) ) {
				return;
			}

			$purchase_details = $this->prepare_offer_purchase_data_pinterest_response( $order_id, $offer_data );
			$cart_details     = $this->prepare_offer_accepted_data_pinterest_response( $offer_data );

			delete_transient( 'wcf-offer-details-for-pinterest-' . $user_key );

			// Apply the filter to check if the 'AddToCart' tracking should be triggered for offer steps.
			$enable_add_to_cart_tracking = apply_filters( 'cartflows_pro_enable_addtocart_for_offer_steps', true, $order_id );

			if ( $enable_add_to_cart_tracking && ! empty( $cart_details ) ) {

				$cart_details  = wp_json_encode( $cart_details );
				$event_script .= "
					<script type='text/javascript'>
						if (typeof pintrk !== 'undefined') {
							pintrk('track', 'AddToCart', $cart_details );
						}
					</script>";
			}

			if ( ! empty( $purchase_details ) ) {

				$purchase_details = wp_json_encode( $purchase_details );
				$event_script    .= "
					<script type='text/javascript'>
						if (typeof pintrk !== 'undefined') {
							pintrk('track', 'Checkout', $purchase_details );
						}
					</script>";
			}

			echo html_entity_decode( $event_script ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Trigger the Snapchat purchase event for the upsell/downsell offer.
	 * 
	 * @return void.
	 */
	public function trigger_offer_purchase_event_for_snapchat() {

		if ( isset( $_GET['wcf-order'] ) && 'enable' === self::$snapchat_pixel_settings['enable_snapchat_purchase_event'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$order_id = intval( $_GET['wcf-order'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$user_key = WC()->session->get_customer_id();

			$offer_data   = get_transient( 'wcf-offer-details-for-snapchat-' . $user_key );
			$event_script = '';

			if ( empty( $offer_data ) ) {
				return;
			}

			$purchase_details = $this->prepare_offer_purchase_data_snapchat_response( $order_id, $offer_data );
			$cart_details     = $this->prepare_offer_accepted_data_snapchat_response( $offer_data );

			delete_transient( 'wcf-offer-details-for-snapchat-' . $user_key );

			if ( ! empty( $cart_details ) ) {

				$cart_details  = wp_json_encode( $cart_details );
				$event_script .= "
					<script type='text/javascript'>
						setTimeout(function () {
							snaptr('track', 'ADD_CART', $cart_details );
						}, 1300);
					</script>";
			}

			if ( ! empty( $purchase_details ) ) {

				$purchase_details = wp_json_encode( $purchase_details );
				$event_script    .= "
					<script type='text/javascript'>
						snaptr('track', 'PURCHASE', $purchase_details );
					</script>";
			}

			echo html_entity_decode( $event_script ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	
	/**
	 * Trigger the view content event for the upsell/downsell offer.
	 *
	 * @param array $params Facebook event parameters array.
	 * @param int   $step_id current step ID.
	 *
	 * @return array
	 */
	public function trigger_offer_viewcontent_event( $params, $step_id ) {

		// Upsell/Downsell Page.
		if ( isset( $step_id ) && wcf()->utils->check_is_offer_page( $step_id ) ) {
			return $this->prepare_viewcontent_data_fb_response( $params, $step_id );
		}

		return $params;
	}

	/**
	 * Prepare view content data for fb response.
	 *
	 * @param array $params Facebook event parameters array.
	 * @param int   $step_id current step id.
	 *
	 * @return array
	 */
	public function prepare_viewcontent_data_fb_response( $params, $step_id ) {

		$product_data   = array();
		$content_ids    = array();
		$category_names = '';
		$product_names  = '';

		// Get offer page data.
		$offer_product = wcf_pro()->utils->get_offer_data( $step_id );

		// Add offer data only if the offer data is array and set.
		if ( ! empty( $offer_product ) && is_array( $offer_product ) ) {

			$content_ids[]  = $offer_product['id'];
			$category_names = wp_strip_all_tags( wc_get_product_category_list( $offer_product['id'] ) );

			$product_data = array(
				'cart_contents'  => array(
					'id'       => $offer_product['id'],
					'name'     => $offer_product['name'],
					'price'    => $offer_product['price'],
					'quantity' => $offer_product['qty'],
				),
				'content_ids'    => $offer_product['id'],
				'product_names'  => $offer_product['name'],
				'category_names' => $category_names,
			);

			$params['content_ids']  = $product_data['content_ids'];
			$params['currency']     = get_woocommerce_currency();
			$params['value']        = $offer_product['total'];
			$params['content_type'] = 'product';
			$params['contents']     = wp_json_encode( $product_data['cart_contents'] );
		}

		return $params;
	}

	/**
	 * Trigger the view content event for the upsell/downsell offer.
	 *
	 * @param array $params Tiktok event parameters array.
	 * @param int   $step_id current step ID.
	 *
	 * @return array
	 */
	public function trigger_offer_viewcontent_event_for_tiktok( $params, $step_id ) {

		// Upsell/Downsell Page.
		if ( isset( $step_id ) && wcf()->utils->check_is_offer_page( $step_id ) ) {
			return $this->prepare_viewcontent_data_tiktok_response( $params, $step_id );
		}

		return $params;
	}

	/**
	 * Trigger the view content event for the upsell/downsell offer.
	 *
	 * @param array $params Snapchat event parameters array.
	 * @param int   $step_id current step ID.
	 *
	 * @return array
	 */
	public function trigger_offer_viewcontent_event_for_snapchat( $params, $step_id ) {

		// Upsell/Downsell Page.
		if ( isset( $step_id ) && wcf()->utils->check_is_offer_page( $step_id ) ) {
			return $this->prepare_viewcontent_data_snapchat_response( $params, $step_id );
		}

		return $params;
	}

	/**
	 * Prepare view content data for tiktok response.
	 *
	 * @param array $params Tiktok event parameters array.
	 * @param int   $step_id current step id.
	 *
	 * @return array
	 */
	public function prepare_viewcontent_data_tiktok_response( $params, $step_id ) {

		$product_data  = array();
		$product_names = '';

		// Get offer page data.
		$offer_product = wcf_pro()->utils->get_offer_data( $step_id );

		// Add offer data only if the offer data is array and set.
		if ( ! empty( $offer_product ) && is_array( $offer_product ) ) {

			$product_data[] = array(
				'content_id'   => (string) $offer_product['id'],
				'content_name' => $offer_product['name'],
				'content_type' => 'product',
			);

			// Assign TikTok Pixel fields.
			$params['currency'] = get_woocommerce_currency();
			$params['value']    = $offer_product['total'];
			$params['contents'] = $product_data;
		}

		return $params;
	}

	/**
	 * Prepare view content data for Snapchat response.
	 *
	 * @param array $params Snapchat event parameters array.
	 * @param int   $step_id current step id.
	 *
	 * @return array
	 */
	public function prepare_viewcontent_data_snapchat_response( $params, $step_id ) {

		// Get offer page data.
		$offer_product = wcf_pro()->utils->get_offer_data( $step_id );
	
		if ( ! empty( $offer_product ) && is_array( $offer_product ) ) {
			// Assign snapchat Pixel fields.
			$params['currency']      = get_woocommerce_currency();
			$params['item_ids']      = array_column( $offer_product, 'id' );
			$params['item_category'] = implode( ',', array_column( $offer_product, 'category' ) );
			$params['number_items']  = count( $offer_product );
			$params['price']         = $offer_product['total'];
		} 
	
		return $params;
	}

	/**
	 * Trigger the purchase event for the upsell/downsell offer.
	 */
	public function trigger_offer_purchase_event_for_ga() {

		if ( isset( $_GET['wcf-order'] ) && 'enable' === self::$ga_settings['enable_add_payment_info'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$order_id = intval( $_GET['wcf-order'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$user_key   = WC()->session->get_customer_id();
			$offer_data = get_transient( 'wcf-offer-details-for-ga-' . $user_key );

			if ( empty( $offer_data ) ) {
				return;
			}

			$purchase_details = $this->prepare_offer_purchase_data_ga_response( $order_id, $offer_data );
			delete_transient( 'wcf-offer-details-for-ga-' . $user_key );

			if ( ! empty( $purchase_details ) ) {

				$purchase_data = wp_json_encode( $purchase_details );

				$event_script = "
					<script type='text/javascript'>
						gtag( 'event', 'purchase', $purchase_data );
					</script>";

				// Reason for PHPCS ignore: Printing Google Analytics script. No input taken from the user except GA ID.
				echo html_entity_decode( $event_script ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Trigger the purchase event for the upsell/downsell offer.
	 *
	 * @since x.x.x
	 * @return void.
	 */
	public function trigger_offer_purchase_event_for_gads() {

		if ( isset( $_GET['wcf-order'] ) && isset( self::$gads_settings['google_ads_tracking'] ) && 'enable' === self::$gads_settings['google_ads_tracking'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$order_id = intval( $_GET['wcf-order'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$user_key   = WC()->session->get_customer_id();
			$offer_data = get_transient( 'wcf-offer-details-for-gads-' . $user_key );

			if ( empty( $offer_data ) ) {
				return;
			}

			$purchase_details = $this->prepare_offer_purchase_data_gads_response( $order_id, (array) $offer_data );
			delete_transient( 'wcf-offer-details-for-gads-' . $user_key );

			if ( ! empty( $purchase_details ) ) {

				$purchase_data = wp_json_encode( $purchase_details );

				$event_script = "
					<script type='text/javascript'>
						gtag( 'event', 'purchase', $purchase_data );
					</script>";

				// Reason for PHPCS ignore: Printing Google Analytics script. No input taken from the user except GA ID.
				echo html_entity_decode( $event_script ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Prepare the purchase event data for the facebook pixel.
	 *
	 * @param integer $order_id order id.
	 * @param array   $offer_data offer data.
	 */
	public function prepare_offer_purchase_data_fb_response( $order_id, $offer_data ) {

		$purchase_data = array();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$purchase_data['content_type'] = 'product';
		$purchase_data['currency']     = wcf()->options->get_checkout_meta_value( $order_id, '_order_currency' );
		$purchase_data['userAgent']    = wcf()->options->get_checkout_meta_value( $order_id, '_customer_user_agent' );
		$purchase_data['plugin']       = 'CartFlows-Offer';

		$purchase_data['content_ids'][]      = (string) $product_data['id'];
		$purchase_data['content_names'][]    = $product_data['name'];
		$purchase_data['content_category'][] = wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) );
		$purchase_data['value']              = $product_data['total'];
		$purchase_data['transaction_id']     = $offer_data['order_id'];

		if ( ! wcf_pro()->utils->is_separate_offer_order() ) {
			$purchase_data['transaction_id'] = $offer_data['order_id'] . '_' . $product_data['step_id'];
		}

		return $purchase_data;
	}

	/**
	 * Prepare the purchase event data for the tiktok pixel.
	 *
	 * @param integer $order_id order id.
	 * @param array   $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_purchase_data_tiktok_response( $order_id, $offer_data ) {

		$purchase_data = array();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$currency                   = wcf()->options->get_checkout_meta_value( $order_id, '_order_currency' );
		$purchase_data['currency']  = $currency ? $currency : get_woocommerce_currency();
		$purchase_data['userAgent'] = wcf()->options->get_checkout_meta_value( $order_id, '_customer_user_agent' );
		$purchase_data['plugin']    = 'CartFlows-Offer';

		// Setting up the contents array for TikTok.
		$purchase_data['contents'][] = array(
			'content_id'       => (string) $product_data['id'],
			'content_name'     => $product_data['name'],
			'content_type'     => 'product',
			'content_category' => wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) ),
		);

		$purchase_data['value']          = $product_data['total'];
		$purchase_data['transaction_id'] = $offer_data['order_id'];

		if ( ! wcf_pro()->utils->is_separate_offer_order() ) {
			$purchase_data['transaction_id'] = $offer_data['order_id'] . '_' . $product_data['step_id'];
		}

		return $purchase_data;
	}

	/**
	 * Prepare the checkout event data for the pinterest tag.
	 *
	 * @param integer $order_id order id.
	 * @param array   $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_purchase_data_pinterest_response( $order_id, $offer_data ) {

		$purchase_data = array();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$currency                        = wcf()->options->get_checkout_meta_value( $order_id, '_order_currency' );
		$purchase_data['currency']       = $currency ? $currency : get_woocommerce_currency();
		$purchase_data['value']          = $product_data['total'];
		$purchase_data['order_quantity'] = $product_data['qty'];
		$purchase_data['event_id']       = 'eventId' . $order_id;
		$purchase_data['event_name']     = 'checkout';

		$purchase_data['line_items'][] = array(
			'product_name'     => $product_data['name'],
			'product_id'       => (string) $product_data['id'],
			'product_price'    => $product_data['price'],
			'product_quantity' => $product_data['qty'],
			'product_category' => wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) ),
		);

		$purchase_data['transaction_id'] = $offer_data['order_id'];

		if ( ! wcf_pro()->utils->is_separate_offer_order() ) {
			$purchase_data['transaction_id'] = $offer_data['order_id'] . '_' . $product_data['step_id'];
		}

		return $purchase_data;
	}

	/**
	 * Prepare the purchase event data for the snapchat pixel.
	 *
	 * @param integer $order_id order id.
	 * @param array   $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_purchase_data_snapchat_response( $order_id, $offer_data ) {

		$purchase_data = Cartflows_Tracking::get_instance()->prepare_common_data_snapchat_response();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$currency = wcf()->options->get_checkout_meta_value( $order_id, '_order_currency' );

		$purchase_data['currency']       = $currency ? $currency : get_woocommerce_currency();
		$purchase_data['price']          = $product_data['total'];
		$purchase_data['item_ids']       = array( (string) $product_data['id'] );
		$purchase_data['item_category']  = wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) );
		$purchase_data['number_items']   = WC()->cart->get_cart_contents_count();
		$purchase_data['transaction_id'] = $offer_data['order_id'];

		if ( ! wcf_pro()->utils->is_separate_offer_order() ) {
			$purchase_data['transaction_id'] = $offer_data['order_id'] . '_' . $product_data['step_id'];
		}

		return $purchase_data;
	}

	/**
	 * Prepare the addtocart event data for the pinterest tag.
	 *
	 * @param array $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_accepted_data_pinterest_response( $offer_data ) {

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return array();
		}

		$purchase_data = array(
			'value'          => floatval( $product_data['total'] ),
			'order_quantity' => intval( $product_data['qty'] ),
			'currency'       => get_woocommerce_currency(),
			'event_id'       => 'eventId' . gmdate( 'YmdHis' ),
			'line_items'     => array(
				array(
					'product_name'     => $product_data['name'],
					'product_id'       => (string) $product_data['id'],
					'product_price'    => floatval( $product_data['price'] ),
					'product_quantity' => intval( $product_data['qty'] ),
					'product_category' => wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) ),
				),
			),
		);

		return $purchase_data;
	}

	/**
	 * Prepare the addtocart event data for the tiktok pixel.
	 *
	 * @param array $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_accepted_data_tiktok_response( $offer_data ) {

		$purchase_data = array();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$purchase_data['currency'] = get_woocommerce_currency();

		$purchase_data['plugin'] = 'CartFlows-Offer';

		// Setting up the contents array for TikTok.
		$purchase_data['contents'][] = array(
			'content_id'       => (string) $product_data['id'],
			'content_name'     => $product_data['name'],
			'content_type'     => 'product',
			'content_category' => wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) ),
		);

		$purchase_data['value'] = $product_data['total'];

		return $purchase_data;
	}

	/**
	 * Prepare the addtocart event data for the tiktok pixel.
	 *
	 * @param array $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_accepted_data_snapchat_response( $offer_data ) {

		$cart_data = Cartflows_Tracking::get_instance()->prepare_common_data_snapchat_response();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $cart_data;
		}

		$cart_data['item_ids']      = array( (string) $product_data['id'] );
		$cart_data['item_category'] = wp_strip_all_tags( wc_get_product_category_list( $product_data['id'] ) );
		$cart_data['number_items']  = $product_data['qty'];
		$cart_data['price']         = $product_data['total'];

		return $cart_data;
	}

	/**
	 * Prepare the purchase event data for the google analytics.
	 *
	 * @param integer $order_id order id.
	 * @param array   $offer_data offer data.
	 */
	public function prepare_offer_purchase_data_ga_response( $order_id, $offer_data ) {

		$purchase_data = array();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$ga_tracking_id = esc_attr( self::$ga_settings['google_analytics_id'] );

		$shipping_tax = $product_data['shipping_fee_tax'] - $product_data['shipping_fee'];
		$products_tax = $product_data['qty'] * ( $product_data['unit_price_tax'] - intval( $product_data['unit_price'] ) );

		$purchase_data = array(
			'send_to'         => $ga_tracking_id,
			'event_category'  => 'Enhanced-Ecommerce',
			'transaction_id'  => $offer_data['order_id'],
			'affiliation'     => get_bloginfo( 'name' ),
			'value'           => $this->format_number( $product_data['total'] ),
			'currency'        => wcf()->options->get_checkout_meta_value( $order_id, '_order_currency' ),
			'shipping'        => $product_data['shipping_fee_tax'],
			'tax'             => $this->format_number( $shipping_tax + $products_tax ),
			'items'           => array(
				array(
					'id'       => $product_data['id'],
					'name'     => $product_data['name'],
					'quantity' => $product_data['qty'],
					'price'    => $this->format_number( $product_data['unit_price_tax'] ),
				),
			),
			'non_interaction' => true,
		);

		if ( ! wcf_pro()->utils->is_separate_offer_order() ) {
			$purchase_data['transaction_id'] = $offer_data['order_id'] . '_' . $product_data['step_id'];
		}

		return $purchase_data;
	}

	/**
	 * Prepare the purchase event data for the google ads.
	 *
	 * @since x.x.x
	 * @param integer $order_id order id.
	 * @param array   $offer_data offer data.
	 * @return array
	 */
	public function prepare_offer_purchase_data_gads_response( $order_id, $offer_data ) {

		$purchase_data = array();

		$product_data = $offer_data['offer_product'];

		if ( empty( $product_data ) ) {
			return $purchase_data;
		}

		$gads_tracking_id      = sanitize_text_field( self::$gads_settings['google_ads_id'] );
		$gads_conversion_label = sanitize_text_field( self::$gads_settings['google_ads_label'] );
		$shipping_tax          = $product_data['shipping_fee_tax'] - $product_data['shipping_fee'];
		$products_tax          = $product_data['qty'] * ( $product_data['unit_price_tax'] - floatval( $product_data['unit_price'] ) );

		$purchase_data = array(
			'send_to'         => $gads_tracking_id . '/' . $gads_conversion_label,
			'event_category'  => 'Enhanced-Ecommerce',
			'transaction_id'  => $offer_data['order_id'],
			'affiliation'     => get_bloginfo( 'name' ),
			'value'           => $this->format_number( $product_data['total'] ),
			'currency'        => wcf()->options->get_checkout_meta_value( $order_id, '_order_currency' ),
			'shipping'        => $product_data['shipping_fee_tax'],
			'tax'             => $this->format_number( $shipping_tax + $products_tax ),
			'items'           => array(
				array(
					'id'       => $product_data['id'],
					'name'     => $product_data['name'],
					'quantity' => $product_data['qty'],
					'price'    => $this->format_number( $product_data['unit_price_tax'] ),
				),
			),
			'non_interaction' => true,
		);

		if ( ! wcf_pro()->utils->is_separate_offer_order() ) {
			$purchase_data['transaction_id'] = $offer_data['order_id'] . '_' . $product_data['step_id'];
		}

		return $purchase_data;
	}


	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 */
	public function trigger_ga_add_to_cart_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['ga_added_to_cart_data'] = $this->prepare_ga_response( $product_id );

				if ( 'enable' === self::$ga_settings['enable_add_payment_info'] ) {
					$data['ga_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_ga_response() );
				}

				return $data;
			}
		);

	}

	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 * @return void
	 */
	public function trigger_gads_add_to_cart_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['gads_added_to_cart_data'] = $this->prepare_gads_response( $product_id );
				if ( isset( self::$gads_settings['google_ads_tracking'] ) && 'enable' === self::$gads_settings['google_ads_tracking'] ) {
					$data['gads_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_gads_response() );
				}
				return $data;
			}
		);
	}

	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 */
	public function trigger_ga_remove_from_cart_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {

				$product_data = array();
				if ( isset( $data['removed_order_bump_data'] ) ) {
					$product_data = $data['removed_order_bump_data'];
				}
				$data['ga_remove_to_cart_data'] = $this->prepare_ga_response( $product_id, $product_data );

				if ( 'enable' === self::$ga_settings['enable_add_payment_info'] ) {
					$data['ga_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_ga_response() );
				}

				return $data;
			}
		);
	}

	/**
	 * Add updated cart and product in Ajax response.
	 *
	 * @param integer $product_id product id.
	 * @return void
	 */
	public function update_cart_data_for_gads_event( $product_id ) {

		add_filter(
			'woocommerce_update_order_review_fragments',
			function( $data ) use ( $product_id ) {
				$data['gads_remove_to_cart_data'] = $this->prepare_gads_response( $product_id );
				if ( isset( self::$gads_settings['google_ads_tracking'] ) && 'enable' === self::$gads_settings['google_ads_tracking'] ) {
					$data['gads_add_payment_info_data'] = wp_json_encode( Cartflows_Tracking::get_instance()->prepare_cart_data_gads_response() );
				}
				return $data;
			}
		);

	}

	/**
	 * Prepare response for facebook.
	 *
	 * @param integer $product_id product id.
	 * @return array
	 */
	public function prepare_fb_response( $product_id ) {

		$response     = array();
		$product_data = array();
		$product      = wc_get_product( $product_id );
		$items        = WC()->cart->get_cart();

		foreach ( $items as $index => $item ) {
			if ( $item['product_id'] === $product_id ) {
				$product_data = $item;
				break;
			}
		}

		if ( ! empty( $product_data ) ) {

			$add_to_cart['content_type']       = 'product';
			$add_to_cart['plugin']             = 'CartFlows-OrderBump';
			$add_to_cart['user_roles']         = implode( ', ', wp_get_current_user()->roles );
			$add_to_cart['content_category'][] = wp_strip_all_tags( wc_get_product_category_list( $product->get_id() ) );
			$add_to_cart['currency']           = get_woocommerce_currency();
			$add_to_cart['value']              = $this->format_number( $product_data['line_subtotal'] + $product_data['line_subtotal_tax'] );
			$add_to_cart['content_name']       = $product->get_title();
			$add_to_cart['content_ids'][]      = (string) $item['product_id'];

			$add_to_cart['contents'] = wp_json_encode(
				array(
					array(
						'id'         => $product_data['product_id'],
						'name'       => $product->get_title(),
						'quantity'   => $product_data['quantity'],
						'item_price' => $this->format_number( $product_data['line_subtotal'] + $product_data['line_subtotal_tax'] ),
					),
				)
			);

			// Put it in single variable.
			$response['added_to_cart'] = $add_to_cart;
		}

		return $response;

	}

	/**
	 * Prepare response for tiktok.
	 *
	 * @param integer $product_id product id.
	 * @return array
	 */
	public function prepare_tiktok_response( $product_id ) {

		$response     = array();
		$product_data = array();
		$product      = wc_get_product( $product_id );
		$items        = WC()->cart->get_cart();

		foreach ( $items as $index => $item ) {
			if ( $item['product_id'] === $product_id ) {
				$product_data = $item;
				break;
			}
		}

		if ( ! empty( $product_data ) ) {
			$add_to_cart['content_type'] = 'product';
			$add_to_cart['currency']     = get_woocommerce_currency();
			$add_to_cart['value']        = $this->format_number( $product_data['line_subtotal'] + $product_data['line_subtotal_tax'] );

			// Building the contents array for TikTok.
			$add_to_cart['contents'] = array(
				array(
					'content_id'   => (string) $item['product_id'],
					'content_name' => $product->get_title(),
					'quantity'     => $product_data['quantity'],
					'price'        => $this->format_number( $product_data['line_subtotal'] + $product_data['line_subtotal_tax'] ),
					'content_type' => 'product',
				),
			);

			// Add other required properties.
			$add_to_cart['plugin']     = 'CartFlows-OrderBump';
			$add_to_cart['user_roles'] = implode( ', ', wp_get_current_user()->roles );

			// Put it in a single variable.
			$response['added_to_cart'] = $add_to_cart;
		}

		return $response;
	}

	/**
	 * Prepare response for pinterest.
	 *
	 * @param integer $product_id product id.
	 * @return array
	 */
	public function prepare_pinterest_response( $product_id ) {

		$response     = array();
		$product_data = array();
		$product      = wc_get_product( $product_id );
		$items        = WC()->cart->get_cart();

		foreach ( $items as $index => $item ) {
			if ( $item['product_id'] === $product_id ) {
				$product_data = $item;
				break;
			}
		}

		if ( ! empty( $product_data ) ) {
			$add_to_cart = array(
				'product_name'     => $product->get_title(),
				'product_id'       => (string) $product_id,
				'product_price'    => $this->format_number( $product_data['line_subtotal'] + $product_data['line_subtotal_tax'] ),
				'product_quantity' => $product_data['quantity'],
			);

			// Put it in a single variable.
			$response['added_to_cart'] = $add_to_cart;
		}

		return $response;
	}

	/**
	 * Prepare response for tiktok.
	 *
	 * @param integer $product_id product id.
	 * @return array
	 */
	public function prepare_snapchat_response( $product_id ) {

		$response     = Cartflows_Tracking::get_instance()->prepare_common_data_snapchat_response();
		$product_data = array();
		$product      = wc_get_product( $product_id );
		$items        = WC()->cart->get_cart();
	
		foreach ( $items as $index => $item ) {
			if ( $item['product_id'] === $product_id ) {
				$product_data = $item;
				break;
			}
		}
	
		if ( ! empty( $product_data ) ) {
			$add_to_cart['currency']      = get_woocommerce_currency();
			$add_to_cart['price']         = $this->format_number( $product_data['line_subtotal'] + $product_data['line_subtotal_tax'] );
			$add_to_cart['description']   = 'CartFlows-OrderBump';
			$add_to_cart['item_ids']      = array( (string) $item['product_id'] );
			$add_to_cart['item_category'] = wp_strip_all_tags( wc_get_product_category_list( $item['product_id'] ) );
			$add_to_cart['number_items']  = '1';

			// Put it in a single variable.
			$response['added_to_cart'] = $add_to_cart;
		}
	
		return $response;
	}

	/**
	 * Prepare response for Google Analytics for Bump Order.
	 *
	 * @param integer $product_id product id.
	 * @param array   $product_data product data.
	 */
	public function prepare_ga_response( $product_id, $product_data = array() ) {

		$response = array();
		$data     = array(
			'quantity' => 1,
			'price'    => 0,
		);

		$product = wc_get_product( $product_id );

		if ( $product ) {

			$items = WC()->cart->get_cart();

			foreach ( $items as $index => $item ) {
				if ( $item['product_id'] === $product_id ) {
					$data['quantity'] = $item['quantity'];
					$data['price']    = $item['line_subtotal'] + $item['line_subtotal_tax'];
					break;
				}
			}

			// For remove from cart event of the order bump.
			if ( ! empty( $product_data ) ) {
				$data['quantity'] = $product_data['quantity'];
				$data['price']    = $product_data['line_subtotal'] + $product_data['line_subtotal_tax'];
			}

			$add_to_cart_ob = array(
				'send_to'         => self::$ga_settings['google_analytics_id'],
				'event_category'  => 'Enhanced-Ecommerce',
				'currency'        => get_woocommerce_currency(),
				'value'           => $this->format_number( $data['price'] ),
				'items'           => array(
					array(
						'id'       => $product_id,
						'name'     => $product->get_title(),
						'sku'      => $product->get_sku(),
						'category' => wp_strip_all_tags( wc_get_product_category_list( $product->get_id() ) ),
						'price'    => $this->format_number( $data['price'] ),
						'quantity' => $data['quantity'],
					),
				),
				'non_interaction' => true,
			);

			$response['add_to_cart']      = wp_json_encode( $add_to_cart_ob );
			$response['remove_from_cart'] = wp_json_encode( $add_to_cart_ob );
		}

		return $response;
	}

	/**
	 * Prepare response for Google Ads.
	 *
	 * @since x.x.x
	 * @param integer $product_id product id.
	 * @param array   $product_data product data.
	 * @return array
	 */
	public function prepare_gads_response( $product_id, $product_data = array() ) {

		$response = array();
		$data     = array(
			'quantity' => 1,
			'price'    => 0,
		);

		$product = wc_get_product( $product_id );

		if ( $product ) {

			$items = WC()->cart->get_cart();

			foreach ( $items as $index => $item ) {
				if ( intval( $item['product_id'] ) === intval( $product_id ) ) {
					$data['quantity'] = $item['quantity'];
					$data['price']    = $item['line_subtotal'] + $item['line_subtotal_tax'];
					break;
				}
			}

			// For remove from cart event of the order bump.
			if ( ! empty( $product_data ) ) {
				$data['quantity'] = $product_data['quantity'];
				$data['price']    = $product_data['line_subtotal'] + $product_data['line_subtotal_tax'];
			}
			$gads_tracking_id      = sanitize_text_field( self::$gads_settings['google_ads_id'] );
			$gads_conversion_label = sanitize_text_field( self::$gads_settings['google_ads_label'] );
			$add_to_cart_ob        = array(
				'send_to'         => $gads_tracking_id . '/' . $gads_conversion_label,
				'event_category'  => 'Enhanced-Ecommerce',
				'currency'        => get_woocommerce_currency(),
				'value'           => $this->format_number( $data['price'] ),
				'items'           => array(
					array(
						'id'       => $product_id,
						'name'     => $product->get_title(),
						'sku'      => $product->get_sku(),
						'category' => wp_strip_all_tags( wc_get_product_category_list( $product->get_id() ) ),
						'price'    => $this->format_number( $data['price'] ),
						'quantity' => $data['quantity'],
					),
				),
				'non_interaction' => true,
			);

			$response['add_to_cart']      = wp_json_encode( $add_to_cart_ob );
			$response['remove_from_cart'] = wp_json_encode( $add_to_cart_ob );
		}

		return $response;
	}


	/**
	 *  Get decimal of price.
	 *
	 * @param integer|float $price price.
	 */
	public function format_number( $price ) {

		return number_format( floatval( $price ), wc_get_price_decimals(), '.', '' );
	}

}
/**
 *  Prepare if class 'Cartflows_Pro_Frontend' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Pro_Tracking::get_instance();

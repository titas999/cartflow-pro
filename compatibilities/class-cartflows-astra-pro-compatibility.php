<?php
/**
 * Astra Pro Compatibility
 *
 * @package CartFlows
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class for Astra Pro Compatibility
 */
class Cartflows_Astra_Pro_Compatibility {

	/**
	 * Member Variable
	 *
	 * @var Cartflows_Astra_Pro_Compatibility
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @since x.x.x
	 * @return Cartflows_Astra_Pro_Compatibility $instance Object of class.
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

		add_action( 'wp', array( $this, 'add_variation_popup_html_actions' ), 0 );
	}

	/**
	 * Return true/false to show change template option.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function add_variation_popup_html_actions() {

		$is_custom_layout_active = defined( 'ASTRA_EXT_VER' ) && Astra_Ext_Extension::is_active( 'advanced-hooks' ) ? true : false;

		$page_template = get_post_meta( _get_wcf_step_id(), '_wp_page_template', true );

		/**
		 * Remove the variation_popup HTML display from wp_footer and re-add it using astra_footer for compatibility.
		 * If Thrive, Astra Addon's Custom header/footer and CartFlows page template is set to Default
		 * then remove the wp_footer action and re-add it on astra_footer action.
		 *
		 * @since x.x.x
		 */
		if ( class_exists( 'TCB_Post' ) && $is_custom_layout_active && '' === $page_template ) {
			$product_options_object = Cartflows_Pro_Product_Options::get_instance();

			// Remove the default action which is wp_footer.
			remove_action( 'wp_footer', array( $product_options_object, 'variation_popup' ), 10 );

			$action = 'wp_footer';

			// if astra_footer exist then call astra_footer.
			if ( has_action( 'astra_footer' ) ) {
				$action = 'astra_footer';
			}

			// Re-add the same action on the astra_footer if available.
			add_action( $action, array( $product_options_object, 'variation_popup' ), '' );

		}

	}

}

/**
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Astra_Pro_Compatibility::get_instance();

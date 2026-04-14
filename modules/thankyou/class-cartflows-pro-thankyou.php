<?php
/**
 * CartFlows ThankYou
 *
 * @package CartFlows
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'CARTFLOWS_PRO_THANKYOU_DIR', CARTFLOWS_PRO_DIR . 'modules/thankyou/' );

/**
 * Initialization
 *
 * @since 1.0.0
 */
class Cartflows_Pro_Thankyou {

	/**
	 * Member Variable
	 *
	 * @var instance
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
		require_once CARTFLOWS_PRO_THANKYOU_DIR . 'classes/class-cartflows-pro-thankyou-markup.php';
	}
}

/**
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Pro_Thankyou::get_instance();

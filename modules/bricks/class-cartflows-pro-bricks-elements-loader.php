<?php
/**
 * Bricks theme modules
 *
 * @package CartFlows
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Bricks theme modules
 *
 * @since 2.1.0
 */
class Cartflows_Pro_Bricks_Elements_Loader {

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @since 2.1.0
	 * @return object instance
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 2.1.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_elements' ), 999 );
		add_action( 'cartflows_after_create_step', array( $this, 'add_default_content_on_upsell_creation' ), 20, 2 );

		// Register Dynamic Content Tags.
		require_once CARTFLOWS_PRO_DIR . 'modules/bricks/class-cartflows-pro-bricks-dynamic-data.php';
		$this->widget_extend_files();
	}

	/**
	 * Returns Script array.
	 *
	 * @since 2.1.0
	 * @return Array of Module instances.
	 */
	public static function get_module_list() {
		$widget_list = array(
			'class-cartflows-pro-bricks-offer-product-description',
			'class-cartflows-pro-bricks-offer-product-image',
			'class-cartflows-pro-bricks-offer-product-price',
			'class-cartflows-pro-bricks-offer-product-quantity',
			'class-cartflows-pro-bricks-offer-product-title',
			'class-cartflows-pro-bricks-offer-product-variation',
			'class-cartflows-pro-bricks-upsell-layout',
		);

		return $widget_list;
	}

	/**
	 * Extend widget with pro functionality.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function widget_extend_files() {

		require_once CARTFLOWS_PRO_DIR . 'modules/bricks/elements/class-cartflows-pro-bricks-checkout-form-extended.php';
	}


	/**
	 * Include elements files
	 *
	 * Load elements files
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function register_elements() {
		// Bail if Bricks is not available.
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}
		
		$element_files = $this->get_module_list();
		wp_enqueue_style( 'cartflows-pro-bricks-style', CARTFLOWS_PRO_URL . 'modules/bricks/widgets-css/frontend.css', array(), CARTFLOWS_PRO_VER );

		$step_type = get_post_meta( (int) get_the_ID(), 'wcf-step-type', true );

		if ( 'upsell' === $step_type || 'downsell' === $step_type ) {

			wp_enqueue_style(
				'wcf-pro-flexslider',
				wcf_pro()->utils->get_css_url( 'flexslider' ),
				array(),
				CARTFLOWS_PRO_VER
			);

			wp_enqueue_script( 'flexslider' );
		}
		foreach ( $element_files as $file ) {
			$file = CARTFLOWS_PRO_DIR . 'modules/bricks/elements/' . $file . '.php';
			\Bricks\Elements::register_element( $file );
		}
	}

	/**
	 * Add default content on upsell creation.
	 *
	 * @param int    $new_step_id new step id.
	 * @param string $step_type step type.
	 *
	 * @since 2.1.0
	 */
	public function add_default_content_on_upsell_creation( $new_step_id, $step_type ) {
		$default_content = '';
		if ( ! class_exists( 'Cartflows_Helper' ) ) {
			return;
		}
		$page_builder = Cartflows_Helper::get_common_setting( 'default_page_builder' );
		if ( 'bricks-builder' == $page_builder && ( 'upsell' == $step_type || 'downsell' == $step_type ) ) {
			if ( file_exists( CARTFLOWS_PRO_DIR . 'admin-core/assets/importer-data/cartflows-bricks-offer-module.json' ) ) {
				$default_content = file_get_contents( CARTFLOWS_PRO_DIR . 'admin-core/assets/importer-data/cartflows-bricks-offer-module.json' );
			}
			$json_decode = json_decode( (string) $default_content, true );
			update_post_meta( $new_step_id, BRICKS_DB_PAGE_CONTENT, $json_decode );
			update_post_meta( $new_step_id, BRICKS_DB_EDITOR_MODE, 'bricks' );
		}
	}
}
	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Cartflows_Pro_Bricks_Elements_Loader::get_instance();

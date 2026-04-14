<?php
/**
 * Bricks theme compatibility
 *
 * @package CartFlows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for Bricks theme compatibility
 */
class Cartflows_Pro_Bricks_Offer_Product_Description extends \Bricks\Element {
	/**
	 * Category of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public $category = 'CartFlows';

	/**
	 * Name of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */

	public $name = 'bricks-cf-product-description';


	/**
	 * Icon of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public $icon = 'fa-solid fa-paragraph';

	/**
	 * Label of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public function get_label() {
		return esc_html__( 'Product Description ', 'cartflows-pro' );
	}

	/**
	 * Retrieve Widget Keywords.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @return string Widget keywords.
	 */
	public function get_keywords() {
		return array( 'cartflows', 'offer', 'product', 'description', 'short description' );
	}

	/**
	 * Register Offer Product Description Styling Controls.
	 *
	 * @since 2.1.0
	 * @access protected
	 */
	protected function register_product_description_style_controls() {

		$this->controls['short_description'] = array(
			'label' => __( 'Short Description', 'cartflows-pro' ),
			'type'  => 'checkbox',
			'group' => 'offer_product_description',
		);
		
		$this->controls['offer_product_description_styling'] = array(
			'label'     => __( 'Styling', 'cartflows-pro' ),
			'type'      => 'typography',
			'separator' => 'before',
			'css'       => array(
				array(
					'property' => 'font',
					'selector' => '.cartflows-pro-bricks__offer-product-description',
				),
			),
			'group'     => 'offer_product_description',
		);

	}

	/**
	 * Set builder control groups
	 *
	 * @since 2.1.0
	 * @access public
	 */
	public function set_control_groups() {
		$this->control_groups['offer_product_description'] = array(
			'title' => esc_html__( 'Offer Product Description', 'cartflows-pro' ),
			'tab'   => 'content',
		);
	}
	/**
	 * Constructor function.
	 *
	 * @since 2.1.0
	 */
	public function set_controls() {
		$this->register_product_description_style_controls();

	}
	/** 
	 * Render element HTML on frontend
	 * 
	 * If no 'render_builder' function is defined then this code is used to render element HTML in builder, too.
	 */
	public function render() {
		$this->set_attribute( '_root', 'data-element-id', $this->id );
		?>
		<div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> > 
			<div class = "cartflows-pro-bricks__offer-product-description">
				<?php
				if ( isset( $this->settings['short_description'] ) && true === $this->settings['short_description'] ) {
					echo do_shortcode( '[cartflows_offer_product_short_desc]' );
				} else {
					echo do_shortcode( '[cartflows_offer_product_desc]' );
				}
				?>
			</div>
		</div>
		<?php
	}


	
}

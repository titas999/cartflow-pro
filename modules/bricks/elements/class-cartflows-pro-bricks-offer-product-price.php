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
class Cartflows_Pro_Bricks_Offer_Product_Price extends \Bricks\Element {
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

	public $name = 'bricks-cf-product-price';


	/**
	 * Icon of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public $icon = 'fa-solid fa-dollar-sign';

	/**
	 * Label of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public function get_label() {
		return esc_html__( 'Product Price ', 'cartflows-pro' );
	}

	/**
	 * Retrieve Widget Keywords.
	 *
	 * @access public
	 * @since 2.1.0
	 * @return string Widget keywords.
	 */
	public function get_keywords() {
		return array( 'cartflows', 'offer', 'product', 'price' );
	}

	/**
	 * Register Offer Product Description Styling Controls.
	 *
	 * @since 2.1.0
	 * @access protected
	 */
	protected function register_product_price_style_controls() {
		$this->controls['alignment'] = array(
			'label' => __( 'Alignment', 'cartflows-pro' ),
			'type'  => 'align-items',
			'css'   => array(
				array(
					'property' => 'align-items',
					'selector' => '.cartflows-pro-bricks__offer-product-price .wcf-offer-price',
				),
			),
			'group' => 'offer_product_price_styling',
		);

		$this->controls['price_style'] = array(
			'label' => __( 'Typography', 'cartflows-pro' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '.cartflows-pro-bricks__offer-product-price .wcf-offer-price',
				),
			),
			'group' => 'offer_product_price_styling',
		);

	}

	/**
	 * Set builder control groups
	 *
	 * @since 2.1.0
	 * @access public
	 */
	public function set_control_groups() {
		$this->control_groups['offer_product_price_styling'] = array(
			'title' => esc_html__( 'Offer Product Price', 'cartflows-pro' ),
			'tab'   => 'content',
		);

	}
	/**
	 * Constructor function.
	 *
	 * @since 2.1.0
	 */
	public function set_controls() {
		$this->register_product_price_style_controls();
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
			<div class = "cartflows-pro-bricks__offer-product-price">
				<?php
					echo do_shortcode( '[cartflows_offer_product_price]' );
				?>
			</div>
		</div>
		<?php
	}


	
}

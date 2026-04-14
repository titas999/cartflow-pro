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
class Cartflows_Pro_Bricks_Offer_Product_Variation extends \Bricks\Element {
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

	public $name = 'bricks-cf-product-variation';


	/**
	 * Icon of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public $icon = 'fa-solid fa-star';

	/**
	 * Label of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public function get_label() {
		return esc_html__( 'Product Variation ', 'cartflows-pro' );
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
		return array( 'cartflows', 'offer', 'product', 'variation' );
	}

	/**
	 * Register Offer Product Description Styling Controls.
	 *
	 * @since 2.1.0
	 * @access protected
	 */
	protected function register_product_title_style_controls() {
		$this->controls['alignment'] = array(
			'label' => __( 'Alignment', 'cartflows-pro' ),
			'type'  => 'align-items',
			'css'   => array(
				array(
					'property' => 'align-items',
					'selector' => '.cartflows-pro-bricks__offer-product-variation .wcf-embeded-product-variation-wrap',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);

		$this->controls['variation_width'] = array(
			'label' => __( 'Max-Width', 'cartflows-pro' ),
			'type'  => 'number',
			'unit'  => '%',
			'min'   => 0,
			'max'   => 100,
			'css'   => array(
				array(
					'property' => 'max-width',
					'selector' => '.cartflows-pro-bricks__offer-product-variation .wcf-embeded-product-variation-wrap .variations',
				),
			),
			'group' => 'offer_product_variation_styling',
		);
		
		$this->controls['variation_label_style'] = array(
			'label' => __( 'Label Typography', 'cartflows-pro' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '.cartflows-pro-bricks__offer-product-variation label',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);

		

		$this->controls['variation_text_style'] = array(
			'label' => __( 'Input Text Typography', 'cartflows-pro' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '.cartflows-pro-bricks__offer-product-variation .wcf-embeded-product-variation-wrap .variations .value select',
				),
			),
			'group' => 'offer_product_variation_styling',
		);


	}

	/**
	 * Set builder control groups
	 * 
	 * @access public
	 */
	public function set_control_groups() {
		$this->control_groups['offer_product_variation_styling'] = array(
			'title' => esc_html__( 'Offer Product Variation', 'cartflows-pro' ),
			'tab'   => 'content',
		);

	}
	/**
	 * Constructor function.
	 */
	public function set_controls() {
		$this->register_product_title_style_controls();
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
		<div class = "cartflows-pro-bricks__offer-product-variation">
			<?php echo do_shortcode( '[cartflows_offer_product_variation]' ); ?>
		</div>
		</div>
		<?php
	}


	
}

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
class Cartflows_Pro_Bricks_Offer_Product_Quantity extends \Bricks\Element {
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

	public $name = 'bricks-cf-product-quantity';


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
		return esc_html__( 'Product Quantity ', 'cartflows-pro' );
	}

	/**
	 * Retrieve Widget Keywords.
	 *
	 * @access public
	 * @since 2.1.0
	 * @return string Widget keywords.
	 */
	public function get_keywords() {
		return array( 'cartflows', 'offer', 'product', 'quantity' );
	}

	/**
	 * Register Offer Product Description Styling Controls.
	 *
	 * @since 2.1.0
	 * @access protected
	 */
	protected function register_product_quantity_style_controls() {
		$this->controls['alignment'] = array(
			'label' => __( 'Alignment', 'cartflows-pro' ),
			'type'  => 'align-items',
			'css'   => array(
				array(
					'property' => 'align-items',
					'selector' => '.cartflows-pro-elementor__offer-product-quantity .quantity',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);

		$this->controls['quantity_width'] = array(
			'label' => __( 'Max-Width', 'cartflows-pro' ),
			'type'  => 'number',
			'unit'  => '%',
			'min'   => 0,
			'max'   => 100,
			'css'   => array(
				array(
					'property' => 'max-width',
					'selector' => '.cartflows-pro-bricks__offer-product-quantity .quantity',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);

		$this->controls['quantity_label_border'] = array(
			'label' => __( 'Quantity Field Border', 'cartflows-pro' ),
			'type'  => 'border',
			'css'   => array(
				array(
					'property' => 'border',
					'selector' => '.cartflows-pro-bricks__offer-product-quantity .quantity .input-text.qty.text',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);
		
		$this->controls['quantity_label_style'] = array(
			'label' => __( 'Label Typography', 'cartflows-pro' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '.cartflows-pro-bricks__offer-product-quantity .quantity .screen-reader-text',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);

		

		$this->controls['quantity_text_style'] = array(
			'label' => __( 'Text Typography', 'cartflows-pro' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '.cartflows-pro-bricks__offer-product-quantity .quantity .input-text.qty.text',
				),
			),
			'group' => 'offer_product_quantity_styling',
		);


	}

	/**
	 * Set builder control groups
	 *
	 * @since 2.1.0
	 * @access public
	 */
	public function set_control_groups() {
		$this->control_groups['offer_product_quantity_styling'] = array(
			'title' => esc_html__( 'Offer Product Quantity', 'cartflows-pro' ),
			'tab'   => 'content',
		);

	}
	/**
	 * Constructor function.
	 *
	 * @since 2.1.0
	 */
	public function set_controls() {
		$this->register_product_quantity_style_controls();
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
		<div class = "cartflows-pro-bricks__offer-product-quantity">
			<?php echo do_shortcode( '[cartflows_offer_product_quantity]' ); ?>
		</div>
		</div>
		<?php
	}


	
}

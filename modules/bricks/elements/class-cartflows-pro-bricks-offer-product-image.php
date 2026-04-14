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
class Cartflows_Pro_Bricks_Offer_Product_Image extends \Bricks\Element {
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
	 * @var instance
	 */

	public $name = 'bricks-cf-product-image';


	/**
	 * Icon of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public $icon = 'fa-solid fa-image';

	/**
	 * Get the css selector.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public $css_selector = '.cartflows-pro-bricks__offer-product-image .woocommerce-product-gallery .woocommerce-product-gallery__image img';

	/**
	 * Label of the element.
	 *
	 * @since 2.1.0
	 * @var instance
	 */
	public function get_label() {
		return esc_html__( 'Product Image ', 'cartflows-pro' );
	}

	/**
	 * Retrieve Widget Keywords.
	 *
	 * @access public
	 * @since 2.1.0
	 * @return string Widget keywords.
	 */
	public function get_keywords() {
		return array( 'cartflows', 'offer', 'product', 'image' );
	}

	/**
	 * Register Offer Product Description Styling Controls.
	 *
	 * @since 2.1.0
	 * @access protected
	 */
	protected function register_product_image_style_controls() {
		$this->controls['image_spacing'] = array(
			'label' => __( 'Image Spacing', 'cartflows-pro' ),
			'type'  => 'dimensions',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '.cartflows-pro-bricks__offer-product-image .woocommerce-product-gallery .woocommerce-product-gallery__wrapper',
				),
			),
			'group' => 'offer_product_image_styling',
		);

		$this->controls['image_border'] = array(
			'label'       => __( 'Border', 'cartflows-pro' ),
			'type'        => 'border',
			'show_label'  => true,
			'label_block' => true,
			'css'         => array(
				array(
					'property' => 'border-style',
					'selector' => '.cartflows-pro-bricks__offer-product-image .woocommerce-product-gallery .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image img',
				),
			),
			'group'       => 'offer_product_image_styling',
		);

	}

	/**
	 * Register Offer Product Image Style Controls.
	 *
	 * @since 2.1.0
	 * @access protected
	 */
	protected function register_product_thumbnails_style_controls() {

		$this->controls['thumbnail_spacing'] = array(
			'label' => __( 'Spacing between Thumbnails', 'cartflows-pro' ),
			'type'  => 'dimensions',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '.cartflows-pro-bricks__offer-product-image .woocommerce-product-gallery ol li:not(:last-child)',
				),
			),
			'group' => 'offer_thumbnails_styling',
		);

		$this->controls['thumbnail_border'] = array(
			'label'       => __( 'Thumbnail Border', 'cartflows-pro' ),
			'type'        => 'border',
			'show_label'  => true,
			'label_block' => true,
			'name'        => 'thumbnail_border',
			'css'         => array(
				array(
					'property' => 'border',
					'selector' => '.cartflows-pro-bricks__offer-product-image .woocommerce-product-gallery ol li img',
				),
			),

			'group'       => 'offer_thumbnails_styling',
		);

	}

	/**
	 * Set builder control groups
	 *
	 * @since 2.1.0
	 * @access public
	 */
	public function set_control_groups() {
		$this->control_groups['offer_product_image_styling'] = array(
			'title' => esc_html__( 'Offer Product Image', 'cartflows-pro' ),
			'tab'   => 'content',
		);

		$this->control_groups['offer_thumbnails_styling'] = array(
			'title' => esc_html__( 'Thumbnails', 'cartflows-pro' ),
			'tab'   => 'content',
		);


	}
	/**
	 * Constructor function.
	 *
	 * @since 2.1.0
	 */
	public function set_controls() {
		$this->register_product_image_style_controls();
		$this->register_product_thumbnails_style_controls();

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
			<div class = "cartflows-pro-bricks__offer-product-image">
				<?php
					echo do_shortcode( '[cartflows_offer_product_image]' );
				?>
			</div>
		</div>
		<?php
	}


	
}

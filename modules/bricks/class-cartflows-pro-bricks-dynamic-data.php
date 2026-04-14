<?php
/**
 * Cartflows Pro Bricks Dynamic Data.
 *
 * @package Cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class handles bricks dynamic data functionality.
 *
 * @since 2.1.0
 */
class Cartflows_Pro_Bricks_Dynamic_Data {
	/**
	 * Member Variable
	 *
	 * @var object instance
	 *
	 * @since 2.1.0
	 */
	private static $instance;

	/**
	 *  Initiator
	 *
	 * @return object instance
	 *
	 * @since 2.1.0
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Setup actions and filters.
	 *
	 * @since 2.1.0
	 */
	private function __construct() {
		add_filter( 'cartflows_bricks_dynamic_tags_list', array( $this, 'dynamic_tags_pro' ), 20 );
		add_filter( 'cartflows_bricks_dynamic_data_render_tag', array( $this, 'get_the_tag_value_pro' ), 20, 2 );
	}

	/**
	 * Register Tags.
	 *
	 * @param array $tags Existing tags.
	 *
	 * @since 2.1.0
	 * @return array
	 */
	public function dynamic_tags_pro( $tags = array() ) {
		return array_merge(
			$tags,
			array(
				array(
					'slug'  => 'wcf_product_title',
					'name'  => '{wcf_product_title}',
					'label' => esc_html__( 'Product Title', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_product_price',
					'name'  => '{wcf_product_price}',
					'label' => esc_html__( 'Product Price', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_product_description',
					'name'  => '{wcf_product_description}',
					'label' => esc_html__( 'Product Description', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_product_short_description',
					'name'  => '{wcf_product_short_description}',
					'label' => esc_html__( 'Product Short Description', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_product_image',
					'name'  => '{wcf_product_image}',
					'label' => esc_html__( 'Product Image', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_product_quantity',
					'name'  => '{wcf_product_quantity}',
					'label' => esc_html__( 'Product Quantity', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_product_variation',
					'name'  => '{wcf_product_variation}',
					'label' => esc_html__( 'Product Variation', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_accept_offer',
					'name'  => '{wcf_accept_offer}',
					'label' => esc_html__( 'Accept Offer', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),
				array(
					'slug'  => 'wcf_reject_offer',
					'name'  => '{wcf_reject_offer}',
					'label' => esc_html__( 'Reject Offer', 'cartflows-pro' ),
					'group' => esc_html__( 'CartFlows', 'cartflows-pro' ),
				),

			)
		);
	}

	/**
	 * Parse tag
	 *
	 * @since 2.1.0
	 * @param string $name    Tag name.
	 * @param object $post    Post object.
	 * @return string|void
	 */
	public function parse_tag( $name, $post ) {
		$post_id = $post->ID;

		$product = wcf_pro()->utils->get_offer_data( $post_id );

		// Handle cases where the product is not valid.
		if ( empty( $product ) ) {
			return;
		}

		// Map specific product-related tags to their corresponding HTML.
		$tag_to_html = array(
			'wcf_product_title'             => '<div class="cartflows-bricks-offer-product-title">' . $product['name'] . '</div>',
			'wcf_product_price'             => '<div class="cartflows-bricks-offer-product-price">' . wc_price( $product['display_price'] ) . '</div>',
			'wcf_product_description'       => '<div class="cartflows-bricks-offer-product-description">' . $product['desc'] . '</div>',
			'wcf_product_short_description' => '<div class="cartflows-bricks-offer-product-short-description">' . $product['short_desc'] . '</div>',
		);

		if ( isset( $tag_to_html[ $name ] ) ) {
			return $tag_to_html[ $name ];
		}

		// Handle specific non-product related tags.
		switch ( $name ) {
			case 'wcf_product_image':
				$product_image = Cartflows_Pro_Base_Offer_Shortcodes::get_instance()->product_image( $post );
				return '<div class="cartflows-bricks-offer-product-image">' . $product_image . '</div>';

			case 'wcf_product_quantity':
				$quantity_selector = Cartflows_Pro_Base_Offer_Shortcodes::get_instance()->quantity_selector( $post );
				return '<div class="cartflows-bricks-offer-product-quantity">' . $quantity_selector . '</div>';

			case 'wcf_product_variation':
				$variation_selector = Cartflows_Pro_Base_Offer_Shortcodes::get_instance()->variation_selector( $post );
				return '<div class="cartflows-bricks-offer-product-variations">' . $variation_selector . '</div>';

			case 'wcf_accept_offer':
				return '?class=wcf-up-offer-yes';

			case 'wcf_reject_offer':
				return '?class=wcf-up-offer-no';
		}

		return $name;
	}


	/**
	 * Main function to render the tag value for WooCommerce provider
	 *
	 * @param string $tag    Tag name.
	 * @param object $post   Post object.
	 *
	 * @since 2.1.0
	 * @return string|void
	 */
	public function get_the_tag_value_pro( $tag, $post ) {
		// Get all the registered tags and check if the tag exists.
		$registered_tags = $this->dynamic_tags_pro();
		$tag_slug        = strtok( $tag, ':' );

		if ( false === array_search( $tag_slug, array_column( $registered_tags, 'slug' ), true ) ) {
			return $tag;
		}

		// Return the parsed tag value.
		return $this->parse_tag( $tag, $post );
	}

}

/**
 * Initiate the class.
 */
Cartflows_Pro_Bricks_Dynamic_Data::get_instance();

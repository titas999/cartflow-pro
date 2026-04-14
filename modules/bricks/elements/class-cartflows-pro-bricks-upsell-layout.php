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
 * Class for Bricks theme compatibility.
 */
class Cartflows_Pro_Bricks_Upsell_Layout extends \Bricks\Element {
	/**
	 * Category of the element.
	 *
	 * @var string
	 */
	public $category = 'CartFlows';

	/**
	 * Name of the element.
	 *
	 * @var string
	 */

	public $name = 'bricks-cf-upsell-layout';


	/**
	 * Icon of the element.
	 *
	 * @var string
	 */
	public $icon = 'ti-tag';

	/**
	 * This is nestable.
	 *
	 * @var bool
	 */
	public $nestable = true;

	/**
	 * Label of the element.
	 *
	 * @since 2.1.0
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'Offer Steps Layout ', 'cartflows-pro' );
	}

	/**
	 * Retrieve Widget Keywords.
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'cartflows', 'offer', 'product', 'layout', 'upsell', 'downsell' );
	}

	/**
	 * Get nestable children.
	 *
	 * @return array children
	 */
	public function get_nestable_children() {

		$left_column_children = array(
			array(
				'name'     => 'block',
				'children' => array(
					array(
						'name'     => 'bricks-cf-product-image',
						'settings' => array(
							'_margin'                 =>
								array(
									'top' => '0',
								),
							'_border'                 => array(
								'radius' => array(
									'top'    => '8',
									'right'  => '8',
									'left'   => '8',
									'bottom' => '8',
								),
							),
							'_width:mobile_landscape' => '100%',
						),
					),
				),
				'settings' => array(
					'_margin:mobile_portrait' => array(
						'top' => 0,
					),

				),

				
				
			),
		);
		$right_column_children = array(
			array(
				'name'     => 'block',
				'children' => array(
					array(
						'name'     => 'bricks-cf-product-title',
						'settings' => array(
							'_typography' => array(
								'font-size'   => '28px',
								'color'       => array(
									'hex' => '#030303',
								),
								'font-family' => 'Inter',
								'font-weight' => 600,
								'text-align'  => 'left',
							),
						),
					),
			
					array(
						'name'     => 'bricks-cf-product-description',
						'label'    => esc_html__( 'Product Description', 'cartflows-pro' ),
						'settings' => array(
							'_typography'       => array(
								'font-size'   => '16px',
								'color'       => array(
									'hex' => '#030303',
								),
								'font-family' => 'Inter',
								'font-weight' => 400,
								'text-align'  => 'left',
							),
							'short_description' => true,

						
						),
					),
					array(
						'name'     => 'text',
						'settings' => array(
							'text'        => '<div><strong>Price</strong>: </div>',
							'_typography' => array(
								'text-transform' => 'capitalize',
								'font-family'    => 'Inter',
								'font-weight'    => '500',
							),
							'_margin'     => array(
								'top' => '23',
							),
							'_display'    => 'inline-block',
							'_alignSelf'  => 'flex-start',
							'_width'      => '44%',
						),
					),
					array(
						'name'     => 'bricks-cf-product-price',
						'label'    => esc_html__( 'Product Price', 'cartflows-pro' ),
						'settings' => array(
							'_typography' => array(
								'text-align'     => 'left',
								'text-transform' => 'capitalize',
								'font-family'    => 'Inter',
								'font-weight'    => '500',
							),
							'_alignSelf'  => 'flex-start',
							'_position'   => 'static',
						),
					),
					array(
						'name'     => 'bricks-cf-product-quantity',
						'label'    => esc_html__( 'Product Quantity', 'cartflows-pro' ),
						'settings' => array(
							'_typography'    =>
							array(
								'font-family' => 'Inter',
								'font-weight' => 600,
								'text-align'  => 'left',
							),
							'_width'         => '1000%',
							'_alignSelf'     => 'center',
							'_margin'        =>
							array(
								'bottom' => '25',
							),
							'alignment'      => 'flex-start',
							'quantity_width' => '25',
							'quantity_width:mobile_landscape' => '15',
							'quantity_width:mobile_portrait' => '20',
						),
					),
					array(
						'name'     => 'button',
						'settings' => array(
							'text'        => 'Add to my order',
							'link'        => array(
								'type'           => 'meta',
								'useDynamicData' => '{wcf_accept_offer}',
							),
							'_alignSelf'  => 'flex-start',
							'_background' => array(
								'color' => array(
									'hex' => '#ff6700',
								),
							),
							'padding'     => array(
								'top'    => '15px',
								'bottom' => '15px',
								'left'   => '15px',
								'right'  => '15px',
							),
							'_typography' => array(
								'color'          => array(
									'hex' => '#ffffff',
								),
								'font-size'      => '14px',
								'font-weight'    => 600,
								'text-align'     => 'center',
								'font-style'     => 'normal',
								'text-transform' => 'uppercase',
							),
							'_position'   => 'static',
							'_display'    => 'inline',
							'circle'      => true,
							'size'        => 'md',
							'_border'     => array(
								'width'  => array(
									'top'    => '1',
									'left'   => '1',
									'right'  => '1',
									'bottom' => '1',
								),
								'style'  => 'solid',
								'color'  => array(
									'hex' => '#ffdd56',
								),
								'radius' => array(
									'top'    => '24',
									'right'  => '24',
									'bottom' => '24',
									'left'   => '24',
								),
							),
							'_padding'    => array(
								'top'    => '12',
								'left'   => '24',
								'right'  => '24',
								'bottom' => '12',
							),
							'_width'      => '100%',
						),
					),
					array(
						'name'     => 'text-link',
						'settings' => array(
							'text'        => "No Thanks, I'll Pass",
							'link'        => array(
								'type'           => 'meta',
								'useDynamicData' => '{wcf_reject_offer}',
							),
							'_alignSelf'  => 'flex-start',
							'_width'      => '100%',
							'align-items' => 'center',
							'_display'    => 'inline-block',
							'padding'     => array(
								'top'    => '15px',
								'bottom' => '15px',
								'left'   => '15px',
								'right'  => '15px',
							),
							'_typography' => array(
								'font-size'   => '12x',
								'font-weight' => 400,
								'text-align'  => 'center',
								'color'       => array(
									'hex' => '#616161',
								),
							),
							'_margin'     => array(
								'top'  => '15',
								'left' => '0',
							),
						),
					),

				),
			),
		);
	
		return array(
			array(
				'name'     => 'section',
				'settings' => array(
					'_padding'                 => array(
						'top'    => '15px',
						'bottom' => '15px',
					),
					'_margin'                  => array(
						'top' => '10px',
					),
					'_padding:tablet_portrait' => array(
						'top'    => '40',
						'right'  => '20',
						'left'   => '20',
						'bottom' => '40',
					),

					'_padding:mobile_portrait' => array(
						'left'   => '20',
						'right'  => '20',
						'top'    => '45',
						'bottom' => '45',
					),

				),
				'children' => array(
					array(
						'name'     => 'container',
						'children' => array(
							array(
								'name'     => 'heading',
								'settings' =>
								array(
									'text'        => 'WAIT!',
									'_typography' =>
									array(
										'text-align'  => 'center',
										'font-family' => 'Inter',
										'font-weight' => '700',
										'font-size'   => '34',
									),
									'_margin'     =>
									array(
										'bottom' => '10',
									),
									'_alignSelf'  => 'center',
									'_typography:mobile_portrait' => array(
										'line-height' => '1.3',
										'font-size'   => '28',
									),
									'tag'         => 'h2',
									'_margin:mobile_portrait' => array(
										'bottom' => '15',
									),
								),
							),
							array(
								'name'     => 'heading',
								'settings' =>
								array(
									'text'        => 'Don’t Miss Out – Save on Our Top Product!',
									'_typography' => array(
										'text-align'  => 'center',
										'font-weight' => '700',
										'font-size'   => '22',
									),
									'_margin'     => array(
										'bottom' => '30',
									),
									'_typography:mobile_portrait' => array(
										'line-height' => '1.3',
										'font-size'   => '18',
									),
									'_alignSelf'  => 'center',
									'tag'         => 'h4',
								),
							),
							

							array(
								'name'     => 'progress-bar',
								
								'settings' => array(
									'bars'               => array(
										array(
											'percentage' => 80,
											'id'         => 'uzvgzt',
											'color'      => array(
												'hex' => '#ff6700',
											),
										),
									),
									'height'             => '10',
									'barBackgroundColor' => array(
										'hex' => '#ffe2cc',
									),
									'_alignSelf'         => 'center',
									'_border'            => array(
										'radius' => array(
											'top'    => '5',
											'right'  => '5',
											'bottom' => '5',
											'left'   => '5',
										),
									),
									'_width'             => '70%',
									'_width:mobile_portrait' => '100%',
								),
							),
						),
					),
					array(
						'name'     => 'container',
						'settings' => array(
							'_direction'                => 'row',
							'_columnGap'                => '60px',
							'_rowGap'                   => '30px',
							'_margin'                   => array(
								'top' => '30px',
							),
							'_border'                   => array(
								array(
									'top'    => '1',
									'right'  => '1',
									'bottom' => '1',
									'left'   => '1',
								),
								'style'  => 'solid',
								'color'  => array(
									'hex' => '#dddddd',
								),
								'radius' => array(
									'top'    => '8',
									'right'  => '8',
									'left'   => '8',
									'bottom' => '8',
								),
							),
							'_padding'                  => array(
								'top'    => '30px',
								'bottom' => '30px',
								'left'   => '30px',
								'right'  => '30px',
							),
							'_alignItems'               => 'center',
							'_padding:mobile_portrait'  => array(
								'left'   => 20,
								'right'  => 20,
								'top'    => 20,
								'bottom' => 20,
							),
		
							'_padding:mobile_landscape' => array(
								'top'    => 20,
								'left'   => 20,
								'right'  => 20,
								'bottom' => 20,
							),
							'_padding:tablet_portrait'  => array(
								'left'   => 20,
								'right'  => 20,
								'top'    => 0,
								'bottom' => 20,
							),
							'_background'               => array(
								'color' => array(
									'hex' => '#ffffff',
								),
							),
		
							
						),
						'children' => array(
							array(
								'name'     => 'block',
								'label'    => esc_html__( 'Column', 'cartflows-pro' ),
								'settings' => array(
									'_width' => '50%',
									'_width:mobile_portrait' => '100%',
									'_width:mobile_landscape' => '100%',
								),
								'children' => $left_column_children,
							),
							array(
								'name'     => 'block',
								'label'    => esc_html__( 'Column', 'cartflows-pro' ),
								'settings' => array(
									'_width'     => '50%',
									'_width:mobile_portrait' => '100%',
									'_width:mobile_landscape' => '100%',
									'_direction' => 'column',
									'_rowGap'    => '0.75em',
								),
								'children' => $right_column_children,
							),
						),
					),
				),
			),
		);
		
	}

	/**
	 * Render element.
	 *
	 * @return void
	 */
	public function render() {
		echo \Bricks\Frontend::render_children( $this ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	

	
}

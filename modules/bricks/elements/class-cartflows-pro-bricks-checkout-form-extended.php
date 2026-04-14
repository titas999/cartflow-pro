<?php
/**
 * Bricks theme compatibility
 *
 * @package CartFlows
 */

/**
 * Class for Bricks theme compatibility
 *
 * @since 2.1.0
 */
class Cartflows_Pro_Bricks_Checkout_Form_Extended {
	/**
	 * Member Variable
	 *
	 * @since 2.1.0
	 * @var object instance
	 */
	private static $instance;

	/**
	 * Initiator
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
	 * Settings
	 *
	 * @since 2.1.0
	 * @var object $settings
	 */
	public static $settings;

	/**
	 * Checkout Settings
	 *
	 * @since 2.1.0
	 * @var object $checkout_settings
	 */
	public static $checkout_settings;

	/**
	 * Setup actions and filters.
	 *
	 * @since 2.1.0
	 */
	private function __construct() {

		// Apply dynamic option filters.
		add_action( 'cartflows_bricks_checkout_options_filters', array( $this, 'dynamic_filters' ), 99, 2 );
		add_filter( 'bricks/elements/bricks-checkout-form/control_groups', array( $this, 'add_custom_controls_groups' ), 98, 1 );
		add_filter( 'bricks/elements/bricks-checkout-form/controls', array( $this, 'add_custom_controls' ), 98, 1 );

	}


	/**
	 * Add custom controls groups.
	 *
	 * @since 2.1.0
	 * @param array $control_groups control groups data.
	 */
	public function add_custom_controls_groups( $control_groups ) {
		$control_groups['section_two_step_section_fields'] = array(
			'title'    => esc_html__( 'Two Step', 'cartflows-pro' ),
			'tab'      => 'content',
			'required' => array( 'layout', '=', 'two-step' ),
		);
		$control_groups['section_two_step_style_fields']   = array(
			'title'    => esc_html__( 'Two Step', 'cartflows-pro' ),
			'tab'      => 'style',
			'required' => array(
				array( 'layout', '=', 'two-step' ),
				array( 'enable_note', '=', true ),
			),
		);

		return $control_groups;
	}
	/**
	 * Add custom controls.
	 *
	 * @since 2.1.0
	 * @param array $controls controls data.
	 */
	public function add_custom_controls( $controls ) {
		$controls['enable_note'] = array(
			'group'   => 'section_two_step_section_fields',
			'label'   => esc_html__( 'Enable Checkout Note', 'cartflows-pro' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'small'   => true,
			'default' => true,
		);
		$controls['note_text']   = array(
			'group'    => 'section_two_step_section_fields',
			'label'    => esc_html__( 'Note Text', 'cartflows-pro' ),
			'type'     => 'text',
			'default'  => esc_html__( 'Get Your FREE copy of CartFlows in just few steps', 'cartflows-pro' ),
			'required' => array( 'enable_note', '=', true ),
		);

		$controls['two_step_section_heading'] = array(
			'label'     => __( 'Steps', 'cartflows-pro' ),
			'type'      => 'heading',
			'separator' => 'before',
			'group'     => 'section_two_step_section_fields',
		);

		$controls['step_one_title_text'] = array(
			'label'   => __( 'Step One Title', 'cartflows-pro' ),
			'type'    => 'text',
			'default' => __( 'Shipping', 'cartflows-pro' ),
			'group'   => 'section_two_step_section_fields',
		);

		$controls['step_one_sub_title_text'] = array(
			'label'   => __( 'Step One Sub Title', 'cartflows-pro' ),
			'type'    => 'text',
			'default' => __( 'Where to ship it?', 'cartflows-pro' ),
			'group'   => 'section_two_step_section_fields',
		);

		$controls['step_two_title_text'] = array(
			'label'   => __( 'Step Two Title', 'cartflows-pro' ),
			'type'    => 'text',
			'default' => __( 'Payment', 'cartflows-pro' ),
			'group'   => 'section_two_step_section_fields',
		);

		$controls['step_two_sub_title_text'] = array(
			'label'   => __( 'Step Two Sub Title', 'cartflows-pro' ),
			'type'    => 'text',
			'default' => __( 'Of your order', 'cartflows-pro' ),
			'group'   => 'section_two_step_section_fields',
		);

		$controls['offer_button_section'] = array(
			'label'     => __( 'Offer Button', 'cartflows-pro' ),
			'type'      => 'heading',
			'separator' => 'before',
			'group'     => 'section_two_step_section_fields',
		);

		$controls['offer_button_title_text'] = array(
			'label'   => __( 'Offer Button Title', 'cartflows-pro' ),
			'type'    => 'text',
			'default' => __( 'For Special Offer Click Here', 'cartflows-pro' ),
			'group'   => 'section_two_step_section_fields',
		);

		$controls['offer_button_subtitle_text'] = array(
			'label'   => __( 'Offer Button Sub Title', 'cartflows-pro' ),
			'type'    => 'text',
			'default' => __( 'Yes! I want this offer!', 'cartflows-pro' ),
			'group'   => 'section_two_step_section_fields',
		);

		$controls['note_text_color'] = array(
			'label'     => __( 'Note Text Color', 'cartflows-pro' ),
			'type'      => 'color',
			'separator' => 'before',
			'group'     => 'section_two_step_style_fields',
			'required'  => array( 'enable_note', '=', true ),
			'css'       => array(
				array(
					'property' => 'color',
					'selector' => '.wcf-embed-checkout-form-two-step .wcf-embed-checkout-form-note',
				),
			),
		);

		$controls['note_bg_color'] = array(
			'label'    => __( 'Note Background Color', 'cartflows-pro' ),
			'type'     => 'color',
			'group'    => 'section_two_step_style_fields',
			'required' => array( 'enable_note', '=', true ),
			'css'      => array(
				array(
					'property' => 'background-color',
					'selector' => '.wcf-embed-checkout-form-two-step .wcf-embed-checkout-form-note',
				),
				array(
					'property' => 'border-top-color',
					'selector' => '.wcf-bricks-checkout-form .wcf-embed-checkout-form-two-step .wcf-embed-checkout-form-note::before',
				),
				array(
					'property' => 'border-color',
					'selector' => '.wcf-bricks-checkout-form .wcf-embed-checkout-form-two-step .wcf-embed-checkout-form-note',
				),
			),
		);

		$controls['note_typography'] = array(
			'label'    => __( 'Note Typography', 'cartflows-pro' ),
			'type'     => 'typography',
			'group'    => 'section_two_step_style_fields',
			'required' => array( 'enable_note', '=', true ),
			'css'      => array(
				array(
					'property' => 'font-family',
					'selector' => '.wcf-embed-checkout-form-two-step .wcf-embed-checkout-form-note',
				),
			),
		);

		return $controls;
	}


	/**
	 * Added dynamic filter.
	 *
	 * @since 2.1.0
	 * @param array $settings settings data.
	 */
	public function dynamic_filters( $settings ) {

		$checkout_id           = get_the_id();
		$enable_checkout_offer = wcf()->options->get_checkout_meta_value( $checkout_id, 'wcf-pre-checkout-offer' );

		if ( 'yes' === $enable_checkout_offer ) {

			$settings['enable_checkout_offer'] = $enable_checkout_offer;

			add_filter(
				'cartflows_bricks_checkout_settings',
				function ( $data_settings ) use ( $settings ) {
					$data_settings = $settings;
					return $data_settings;
				},
				10,
				1
			);
		}

		$checkout_fields = array(

			// Two step texts.
			array(
				'filter_slug'  => 'wcf-checkout-step-one-title',
				'setting_name' => 'step_one_title_text',
			),
			array(
				'filter_slug'  => 'wcf-checkout-step-one-sub-title',
				'setting_name' => 'step_one_sub_title_text',
			),
			array(
				'filter_slug'  => 'wcf-checkout-step-two-title',
				'setting_name' => 'step_two_title_text',
			),
			array(
				'filter_slug'  => 'wcf-checkout-step-two-sub-title',
				'setting_name' => 'step_two_sub_title_text',
			),
			array(
				'filter_slug'  => 'wcf-checkout-offer-button-title',
				'setting_name' => 'offer_button_title_text',
			),
			array(
				'filter_slug'  => 'wcf-checkout-offer-button-sub-title',
				'setting_name' => 'offer_button_subtitle_text',
			),
		);

		if ( isset( $checkout_fields ) && is_array( $checkout_fields ) ) {

			foreach ( $checkout_fields as $key => $field ) {

				$setting_name = $field['setting_name'];

				if ( isset( $settings[ $setting_name ] ) && '' !== $settings[ $setting_name ] ) {

					add_filter(
						'cartflows_checkout_meta_' . $field['filter_slug'],
						function ( $value ) use ( $setting_name, $settings ) {

							$value = $settings[ $setting_name ];

							return $value;
						},
						10,
						1
					);
				}
			}
		}

		add_filter(
			'cartflows_checkout_meta_wcf-checkout-box-note',
			function ( $is_note_enabled ) use ( $settings ) {

				$is_note_enabled = ( isset( $settings['enable_note'] ) && true === $settings['enable_note'] ) ? 'yes' : 'no';
				return $is_note_enabled;
			},
			10,
			1
		);

		if ( isset( $settings['enable_note'] ) && true === $settings['enable_note'] && ( isset( $settings['note_text'] ) && '' !== $settings['note_text'] ) ) {

			add_filter(
				'cartflows_checkout_meta_wcf-checkout-box-note-text',
				function ( $checkout_note_text ) use ( $settings ) {

					$checkout_note_text = $settings['note_text'];
					return $checkout_note_text;
				},
				10,
				1
			);
		}

	}

}

/**
 * Initiate the class.
 */
Cartflows_Pro_Bricks_Checkout_Form_Extended::get_instance();

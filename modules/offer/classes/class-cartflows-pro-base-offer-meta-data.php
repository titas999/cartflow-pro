<?php
/**
 * Optin post meta fields
 *
 * @package CartFlows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta Boxes setup
 */
class Cartflows_Pro_Base_Offer_Meta_Data {


	/**
	 * Instance
	 *
	 * @var Cartflows_Pro_Base_Offer_Meta_Data $instance Class object
	 */
	private static $instance;


	/**
	 * Initiator
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {

		add_filter( 'cartflows_admin_upsell_step_default_meta_fields', array( $this, 'get_offer_step_default_fields' ), 10, 2 );
		add_filter( 'cartflows_admin_downsell_step_default_meta_fields', array( $this, 'get_offer_step_default_fields' ), 10, 2 );

		add_filter( 'cartflows_admin_upsell_step_meta_settings', array( $this, 'get_settings' ), 10, 2 );
		add_filter( 'cartflows_admin_downsell_step_meta_settings', array( $this, 'get_settings' ), 10, 2 );

		add_filter( 'cartflows_admin_upsell_step_meta_fields', array( $this, 'filter_values' ), 10, 2 );
		add_filter( 'cartflows_admin_downsell_step_meta_fields', array( $this, 'filter_values' ), 10, 2 );
	}



	/**
	 * Filter checkout values
	 *
	 * @param  array $options options.
	 * @param  int   $step_id Current Step's ID.
	 */
	public function filter_values( $options, $step_id ) {

		if ( ! empty( $options['wcf-offer-product'][0] ) ) {

			$product_id  = intval( $options['wcf-offer-product'][0] );
			$product_obj = wc_get_product( $product_id );

			if ( $product_obj ) {
				$options['wcf-offer-product'] = array(
					'discount_type'  => wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-discount' ),
					'value'          => $product_id,
					'label'          => $product_obj->get_name(),
					'img_url'        => get_the_post_thumbnail_url( $product_id ),
					'original_price' => Cartflows_Pro_Admin_Helper::get_product_original_price( $product_obj ),
				);
			}
		}

		// If 'wcf-offer-product' is present but empty, reset to an empty string to prevent undefined output.
		if ( array_key_exists( 'wcf-offer-product', $options ) && isset( $options['wcf-offer-product'][0] ) && empty( $options['wcf-offer-product'][0] ) ) {
			$options['wcf-offer-product'] = '';
		}

		return $options;
	}

	/**
	 * Gget_offer_step_default_fields
	 *
	 * @param  array $step_default_fields step default fields.
	 * @param  int   $step_id Post meta.
	 */
	public function get_offer_step_default_fields( $step_default_fields, $step_id ) {

		$step_default_fields = Cartflows_Pro_Default_Meta::get_instance()->get_offer_fields( $step_id );

		return $step_default_fields;
	}

	/**
	 * Page Header Tabs
	 *
	 * @param  array $settings settings.
	 * @param  int   $step_id Post meta.
	 */
	public function get_settings( $settings, $step_id ) {

		$add_tabs = array(
			'products' => array(
				'title'    => __( 'Products', 'cartflows-pro' ),
				'id'       => 'products',
				'class'    => '',
				'icon'     => 'dashicons-format-aside',
				'priority' => 20,
			),
			'settings' => array(
				'title'    => __( 'Settings', 'cartflows-pro' ),
				'id'       => 'settings',
				'class'    => '',
				'icon'     => 'dashicons-format-aside',
				'priority' => 30,
			),
		);

		$settings_data = $this->get_settings_fields( $step_id );

		

		$flow_id = wcf()->utils->get_flow_id_from_step_id( $step_id );
		if ( ! empty( $flow_id ) && Cartflows_Pro_Helper::is_instant_layout_enabled( (int) $flow_id ) && Cartflows_Pro_Helper::is_instant_layout_enabled_for_step( $step_id ) ) {
			
			$add_tabs['design'] = array(
				'title'    => __( 'Design', 'cartflows-pro' ),
				'id'       => 'design',
				'class'    => '',
				'icon'     => 'dashicons-format-aside',
				'priority' => 20,
			);
		}
		$settings = array(
			'tabs'          => $add_tabs,
			'page_settings' => $this->get_page_settings( $step_id ),
			'settings'      => $settings_data,
		);
		if ( ! empty( $flow_id ) && Cartflows_Helper::is_instant_layout_enabled( (int) $flow_id ) && Cartflows_Pro_Helper::is_instant_layout_enabled_for_step( $step_id ) ) {
			$design_settings             = $this->get_design_fields( $step_id );
			$settings['design_settings'] = $design_settings;
		}

		return $settings;
	}

	/**
	 * Get_page_settings
	 *
	 * @param string $step_id step id.
	 */
	public function get_page_settings( $step_id ) {

		$options = $this->get_data( $step_id );

		$settings = array(
			'settings' => array(
				'product' => array(
					'title'    => __( 'Product', 'cartflows-pro' ),
					'priority' => 20,
					'fields'   => array(
						'product-settings-separator'       => array(
							'type' => 'separator',
						),

						'offer-replace-settings'           => ! wcf_pro()->utils->is_separate_offer_order() ?
						array(
							'type'    => 'doc',
							'content' => sprintf(
								/* translators: %1$1s, %2$2s Link to meta */
								__( 'Do you want to cancel the main order on the purchase of upsell/downsell offer?<br>Please set the "Create a new child order" option in the %1$1sOffer Global Settings%2$2s to use the cancel primary order option.', 'cartflows-pro' ),
								'<a href="' . Cartflows_Pro_Helper::get_setting_page_url() . '" target="_blank">',
								'</a>'
							),
						)
							:
						array(
							'type'    => 'toggle',
							'label'   => __( 'Replace Main Order', 'cartflows-pro' ),
							'name'    => 'wcf-replace-main-order',
							'desc'    => sprintf(
								/* translators: %1$1s, %2$2s Link to meta */
								__( 'Note: If "Replace Main Order" option is enabled then on the purchase of upsell/downsell offer it will charge the difference of main order total and this product. %1$1sLearn More >>%2$2s', 'cartflows-pro' ),
								'<a href="https://cartflows.com/docs/replace-main-checkout-order-with-upsell-downsell" target="_blank">',
								'</a>'
							),
							'tooltip' => __( 'Turn this on if you want the original order to be canceled when a customer accepts an upsell or downsell offer.', 'cartflows-pro' ),
						),

						'replace-order-settings-separator' => array(
							'type' => 'separator',
						),

						'skip-offer-settings'              => array(
							'type'  => 'toggle',
							'name'  => 'wcf-skip-offer',
							'label' => __( 'Skip Offer', 'cartflows-pro' ),
							'desc'  => __( 'Exclude the offer if the buyer has previously purchased the selected product.', 'cartflows-pro' ),
						),

					),

				),
			),
		);

		return $settings;
	}

	/**
	 * Get settings data.
	 *
	 * @param  int $step_id Post ID.
	 */
	public function get_settings_fields( $step_id ) {

		$step_type = get_post_meta( $step_id, 'wcf-step-type', true );
		$flow_id   = get_post_meta( $step_id, 'wcf-flow-id', true );

		if ( 'upsell' === $step_type ) {

			$offer_yes_link = wcf()->utils->get_linking_url(
				array( 'class' => 'wcf-up-offer-yes' )
			);

			$offer_no_link = wcf()->utils->get_linking_url(
				array( 'class' => 'wcf-up-offer-no' )
			);
		}

		if ( 'downsell' === $step_type ) {

			$offer_yes_link = wcf()->utils->get_linking_url(
				array( 'class' => 'wcf-down-offer-yes' )
			);

			$offer_no_link = wcf()->utils->get_linking_url(
				array( 'class' => 'wcf-down-offer-no' )
			);
		}

		$options = $this->get_data( $step_id );

		$opt_steps = Cartflows_Pro_Admin_Helper::get_opt_steps( $step_id );

		$settings = array(
			'settings' => array(
				'offer_processing_strings' => array(
					'title'    => __( 'Offer Popup Strings', 'cartflows-pro' ),
					'slug'     => 'offer_processing_strings',
					'priority' => 20,
					'fields'   => array(
						'offer-process-text' => array(
							'type'          => 'text',
							'label'         => __( 'Offer Processing', 'cartflows-pro' ),
							'name'          => 'wcf-offer-order-process-text',
							'placeholder'   => __( 'Processing Order...', 'cartflows-pro' ),
							'display_align' => 'vertical',
						),
						'offer-success-text' => array(
							'type'          => 'text',
							'label'         => __( 'Offer Success', 'cartflows-pro' ),
							'name'          => 'wcf-offer-order-success-text',
							'placeholder'   => __( 'Product Added Successfully.', 'cartflows-pro' ),
							'display_align' => 'vertical',
						),
						'offer-failure-text' => array(
							'type'          => 'text',
							'label'         => __( 'Offer Failure', 'cartflows-pro' ),
							'name'          => 'wcf-offer-order-failure-text',
							'placeholder'   => __( 'Oooops! Your Payment Failed.', 'cartflows-pro' ),
							'display_align' => 'vertical',
						),
						'offer-success-note' => array(
							'type'          => 'text',
							'label'         => __( 'Offer Success Note', 'cartflows-pro' ),
							'name'          => 'wcf-offer-order-success-note',
							'placeholder'   => __( 'Please wait while we process your payment...', 'cartflows-pro' ),
							'display_align' => 'vertical',
						),
					),
				),
				'shortcode'                => array(
					'title'      => __( 'Shortcodes', 'cartflows-pro' ),
					'slug'       => 'shortcodes',
					'priority'   => 40,
					'fields'     => array(
						'offer-accept-link'         => array(
							'type'          => 'text',
							'label'         => __( 'Accept Offer Link', 'cartflows-pro' ),
							'value'         => $offer_yes_link,
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'offer-reject-link'         => array(
							'type'          => 'text',
							'label'         => __( 'Decline Offer Link', 'cartflows-pro' ),
							'value'         => $offer_no_link,
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-variation'         => array(
							'type'          => 'text',
							'label'         => __( 'Product Variation ', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_variation]',
							'help'          => esc_html__( 'Add this shortcode to your offer page for variation selection. If product is variable, it will show variations.', 'cartflows-pro' ),
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-quantity'          => array(
							'type'          => 'text',
							'label'         => __( 'Product Quantity', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_quantity]',
							'help'          => esc_html__( 'Add this shortcode to your offer page for quantity selection.', 'cartflows-pro' ),
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-title'             => array(
							'type'          => 'text',
							'label'         => __( 'Product Title', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_title]',
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-description'       => array(
							'type'          => 'text',
							'label'         => __( 'Product Description', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_desc]',
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-short-description' => array(
							'type'          => 'text',
							'label'         => __( 'Product Short Description', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_short_desc]',
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-price'             => array(
							'type'          => 'text',
							'label'         => __( 'Product Price', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_price]',
							'help'          => __( 'This shortcode will show the products single quantity price.', 'cartflows-pro' ),
							'readonly'      => true,
							'display_align' => 'vertical',
						),
						'product-image'             => array(
							'type'          => 'text',
							'label'         => __( 'Product Image', 'cartflows-pro' ),
							'value'         => '[cartflows_offer_product_image]',
							'readonly'      => true,
							'display_align' => 'vertical',
						),

					),
					'conditions' => array(
						'relation' => 'and',
						'fields'   => array(
							array(
								'name'     => 'instant-layout-style',
								'operator' => '!==',
								'value'    => 'yes',
							),
						),
					),
				),
				'general'                  => array(
					'title'    => __( 'General', 'cartflows-pro' ),
					'slug'     => 'general',
					'priority' => 50,
					'fields'   => array(
						'slug'                    => array(
							'type'          => 'text',
							'name'          => 'step_post_name',
							'label'         => __( 'Step Slug', 'cartflows-pro' ),
							'value'         => get_post_field( 'post_name' ),
							'display_align' => 'vertical',
						),
						'enable-instant-layout'   => array(
							'type'       => 'toggle',
							'name'       => 'wcf-enable-instant-layout',
							'label'      => __( 'Enable Instant Layout', 'cartflows-pro' ),
							'desc'       => __( 'Turn this on to enable the instant layout for this offer step.', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'wcf-disable-step-toggle' => array(
							'type'         => 'toggle',
							'label'        => __( 'Disable step', 'cartflows-pro' ),
							'name'         => 'wcf-disable-step',
							'value'        => $options['wcf-disable-step'],
							'tooltip'      => __( 'Disable the step', 'cartflows-pro' ),
							'is_fullwidth' => true,
						),
					),
				),

				'conditional-redirection'  => array(
					'title'    => __( 'Conditional Redirection', 'cartflows-pro' ),
					'slug'     => 'conditional_redirection',
					'priority' => 10,
					'fields'   => array(
						'offer-yes-next-step' => array(
							'type'            => 'select',
							'label'           => __( 'Offer - Yes Next Step', 'cartflows-pro' ),
							'optgroup'        => array(
								'upsell'   => esc_html__( 'Upsell &lpar;Woo&rpar;', 'cartflows-pro' ),
								'downsell' => esc_html__( 'Downsell &lpar;Woo&rpar;', 'cartflows-pro' ),
								'thankyou' => esc_html__( 'Thankyou &lpar;Woo&rpar;', 'cartflows-pro' ),
							),
							'name'            => 'wcf-yes-next-step',
							'value'           => $options['wcf-yes-next-step'],
							'data-flow-id'    => $flow_id,
							'data-exclude-id' => $step_id,
							'options'         => $opt_steps,
							'display_align'   => 'vertical',
						),

						'offer-next-step-doc' => array(
							'type'            => 'select',
							'label'           => __( 'Offer - No Next Step', 'cartflows-pro' ),
							'optgroup'        => array(
								'upsell'   => esc_html__( 'Upsell &lpar;Woo&rpar;', 'cartflows-pro' ),
								'downsell' => esc_html__( 'Downsell &lpar;Woo&rpar;', 'cartflows-pro' ),
								'thankyou' => esc_html__( 'Thankyou &lpar;Woo&rpar;', 'cartflows-pro' ),
							),
							'name'            => 'wcf-no-next-step',
							'value'           => $options['wcf-no-next-step'],
							'data-flow-id'    => $flow_id,
							'data-exclude-id' => $step_id,
							'options'         => $opt_steps,
							'display_align'   => 'vertical',
						),
						'offer-no-next-step'  => array(
							'type'    => 'doc',
							/* translators: %1$1s: link html start, %2$12: link html end*/
							'content' => sprintf( __( 'For more information about the conditional redirection please %1$1sClick here.%2$2s', 'cartflows-pro' ), '<a href="https://cartflows.com/docs/create-conditional-upsell-downsell/" target="_blank">', '</a>' ),
						),
					),
				),

				'custom-scripts'           => array(
					'title'    => __( 'Custom Script', 'cartflows-pro' ),
					'slug'     => 'custom_scripts',
					'priority' => 30,
					'fields'   => array(
						'wcf-checkout-custom-script' => array(
							'type'          => 'textarea',
							'label'         => __( 'Custom Script', 'cartflows-pro' ),
							'name'          => 'wcf-custom-script',
							'value'         => $options['wcf-custom-script'],
							'display_align' => 'vertical',
						),
						'offer-success-script'       => array(
							'type'          => 'textarea',
							'label'         => __( 'Offer Success Script', 'cartflows-pro' ),
							'name'          => 'wcf-offer-accept-script',
							'value'         => $options['wcf-offer-accept-script'],
							'tooltip'       => __( 'Paste your custom code here if you want something to happen automatically when someone accepts the offer — like tracking or showing a thank-you message.', 'cartflows-pro' ),
							'display_align' => 'vertical',
						),
						'offer-rejected-script'      => array(
							'type'          => 'textarea',
							'label'         => __( 'Offer Rejected Script', 'cartflows-pro' ),
							'name'          => 'wcf-offer-reject-script',
							'value'         => $options['wcf-offer-reject-script'],
							'tooltip'       => __( 'Paste your own script here if you want something to happen when someone says no to the offer.', 'cartflows-pro' ),
							/* translators: %1$1s: link html start, %2$12: link html end*/
							'desc'          => sprintf( __( 'Use {{order_id}}, {{product_id}} & {{quantity}} and more shortcodes to fetch offer details. %1$1sClick here.%2$2s to know more.', 'cartflows-pro' ), '<a href="https://cartflows.com/docs/offers-js-triggers-shortcodes/" target="_blank">', '</a>' ),
							'display_align' => 'vertical',
						),
					),
				),
			),
		);

		if ( wcf_pro_show_deprecated_step_notes() ) {
			$settings['settings']['general']['fields']['step-note'] = array(
				'type'          => 'textarea',
				'name'          => 'wcf-step-note',
				'label'         => __( 'Step Note', 'cartflows-pro' ),
				'value'         => get_post_meta( $step_id, 'wcf-step-note', true ),
				'rows'          => 2,
				'cols'          => 38,
				'display_align' => 'vertical',
			);
		}

		return $settings;
	}

	/**
	 * Get design settings data.
	 *
	 * @param  int $step_id Post ID.
	 * @return array
	 */
	public function get_design_fields( $step_id ) {

		$options  = $this->get_data( $step_id );
		$settings = array(
			'settings' => array(
				'offer-design'      => array(
					'title'    => __( 'Offer Step Design', 'cartflows-pro' ),
					'slug'     => 'offer_step_design',
					'priority' => 20,
					'fields'   => array(
						'offer-primary-color'       => array(
							'type'       => 'color-picker',
							'name'       => 'wcf-offer-primary-color',
							'label'      => __( 'Primary Color', 'cartflows-pro' ),
							'value'      => $options['wcf-offer-primary-color'],
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'offer-heading-font-family' => array(
							'type'          => 'font-family',
							'label'         => esc_html__( 'Font Family', 'cartflows-pro' ),
							'name'          => 'wcf-offer-heading-font-family',
							'value'         => $options['wcf-offer-heading-font-family'],
							'display_align' => 'vertical',
							'conditions'    => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'wcf-instant-offer-section' => array(
							'type'       => 'heading',
							'label'      => __( 'Instant Layout', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),

						'wcf-instant-offer-left-side-bg-color' => array(
							'type'       => 'color-picker',
							'label'      => __( 'Left Column Background Color', 'cartflows-pro' ),
							'name'       => 'wcf-instant-offer-left-side-bg-color',
							'value'      => $options['wcf-instant-offer-left-side-bg-color'],
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'wcf-instant-offer-right-side-bg-color' => array(
							'type'       => 'color-picker',
							'label'      => __( 'Right Column Background Color', 'cartflows-pro' ),
							'name'       => 'wcf-instant-offer-right-side-bg-color',
							'value'      => $options['wcf-instant-offer-right-side-bg-color'],
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
					),
				),
				'offer-text-design' => array(
					'title'    => __( 'Offer Texts & Buttons', 'cartflows-pro' ),
					'slug'     => 'offer_texts_buttons',
					'priority' => 30,
					'fields'   => array(
						'advanced-options'                 => array(
							'type'         => 'toggle',
							'label'        => __( 'Enable Advanced Options', 'cartflows-pro' ),
							'name'         => 'wcf-advance-options-fields',
							'value'        => $options['wcf-advance-options-fields'],
							'is_fullwidth' => true,
						),

						'heading-font-section'             => array(
							'type'       => 'heading',
							'label'      => __( 'Heading', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'heading-title-text'               => array(
							'type'          => 'text',
							'label'         => __( 'Heading Text', 'cartflows-pro' ),
							'name'          => 'wcf-instant-offer-heading-text',
							'value'         => $options['wcf-instant-offer-heading-text'],
							'placeholder'   => esc_html__( 'Wait! Your Order is Not Complete...', 'cartflows-pro' ),
							'display_align' => 'vertical',
							'conditions'    => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),

						),
						'heading-font-color'               => array(
							'type'       => 'color-picker',
							'label'      => __( 'Text Color', 'cartflows-pro' ),
							'name'       => 'wcf-heading-color',
							'value'      => $options['wcf-heading-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'heading-font-family'              => array(
							'type'              => 'font-family',
							'for'               => 'wcf-heading',
							'label'             => esc_html__( 'Font Family', 'cartflows-pro' ),
							'name'              => 'wcf-heading-font-family',
							'value'             => $options['wcf-heading-font-family'],
							'font_weight_name'  => 'wcf-heading-font-weight',
							'font_weight_value' => $options['wcf-heading-font-weight'],
							'display_align'     => 'vertical',
							'conditions'        => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),

						'sub-heading-font-section'         => array(
							'type'       => 'heading',
							'label'      => __( 'Sub Heading', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'sub-heading-title-text'           => array(
							'type'          => 'text',
							'label'         => __( 'Sub Heading Text', 'cartflows-pro' ),
							'name'          => 'wcf-instant-offer-sub-heading-text',
							'value'         => $options['wcf-instant-offer-sub-heading-text'],
							'placeholder'   => esc_html__( 'We have a special one time offer just for you.', 'cartflows-pro' ),
							'display_align' => 'vertical',
							'conditions'    => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),

						),
						'sub-heading-font-color'           => array(
							'type'       => 'color-picker',
							'label'      => __( 'Text Color', 'cartflows-pro' ),
							'name'       => 'wcf-sub-heading-color',
							'value'      => $options['wcf-sub-heading-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'sub-heading-font-family'          => array(
							'type'              => 'font-family',
							'for'               => 'wcf-sub-heading',
							'label'             => esc_html__( 'Font Family', 'cartflows-pro' ),
							'name'              => 'wcf-sub-heading-font-family',
							'value'             => $options['wcf-sub-heading-font-family'],
							'font_weight_name'  => 'wcf-sub-heading-font-weight',
							'font_weight_value' => $options['wcf-sub-heading-font-weight'],
							'display_align'     => 'vertical',
							'conditions'        => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),

						'product-title-font-section'       => array(
							'type'       => 'heading',
							'label'      => __( 'Product Title', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'product-title-font-color'         => array(
							'type'       => 'color-picker',
							'label'      => __( 'Title Color', 'cartflows-pro' ),
							'name'       => 'wcf-product-title-color',
							'value'      => $options['wcf-product-title-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'product-title-font-family'        => array(
							'type'              => 'font-family',
							'for'               => 'wcf-product-title',
							'label'             => esc_html__( 'Font Family', 'cartflows-pro' ),
							'name'              => 'wcf-product-title-font-family',
							'value'             => $options['wcf-product-title-font-family'],
							'font_weight_name'  => 'wcf-product-title-font-weight',
							'font_weight_value' => $options['wcf-product-title-font-weight'],
							'display_align'     => 'vertical',
							'conditions'        => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),

						'product-description-font-section' => array(
							'type'       => 'heading',
							'label'      => __( 'Product Description', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'product-description-font-color'   => array(
							'type'       => 'color-picker',
							'label'      => __( 'Text Color', 'cartflows-pro' ),
							'name'       => 'wcf-product-description-color',
							'value'      => $options['wcf-product-description-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),

						'button-field-section'             => array(
							'type'       => 'heading',
							'label'      => __( 'Accept Offer Button', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),

						'button-field-text'                => array(
							'type'          => 'text',
							'label'         => __( 'Text', 'cartflows-pro' ),
							'name'          => 'wcf-accept-offer-button-text',
							'value'         => $options['wcf-accept-offer-button-text'],
							'placeholder'   => esc_html__( 'Yes, Add This To My Order', 'cartflows-pro' ),
							'display_align' => 'vertical',
							'conditions'    => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),

						),

						'button-font-family'               => array(
							'type'              => 'font-family',
							'for'               => 'wcf-accept-offer-button',
							'label'             => esc_html__( 'Font Family', 'cartflows-pro' ),
							'name'              => 'wcf-accept-offer-button-font-family',
							'value'             => $options['wcf-accept-offer-button-font-family'],
							'font_weight_name'  => 'wcf-accept-offer-button-font-weight',
							'font_weight_value' => $options['wcf-accept-offer-button-font-weight'],
							'conditions'        => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
							'display_align'     => 'vertical',
						),

						'button-font-size'                 => array(
							'type'          => 'number',
							'label'         => __( 'Font Size (In px)', 'cartflows-pro' ),
							'name'          => 'wcf-accept-offer-button-font-size',
							'value'         => $options['wcf-accept-offer-button-font-size'],
							'placeholder'   => '16px',
							'min'           => 0,
							'conditions'    => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
							'display_align' => 'vertical',
						),
				
						'button-text-color'                => array(
							'type'       => 'color-picker',
							'label'      => __( 'Text Color', 'cartflows-pro' ),
							'name'       => 'wcf-accept-offer-button-color',
							'value'      => $options['wcf-accept-offer-button-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						
						'button-bg-color'                  => array(
							'type'       => 'color-picker',
							'label'      => __( 'Background Color', 'cartflows-pro' ),
							'name'       => 'wcf-accept-offer-button-bg-color',
							'value'      => $options['wcf-accept-offer-button-bg-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'reject-link-field-section'        => array(
							'type'       => 'heading',
							'label'      => __( 'Reject Offer Link', 'cartflows-pro' ),
							'conditions' => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
						'reject-link-text'                 => array(
							'type'          => 'text',
							'label'         => __( 'Text', 'cartflows-pro' ),
							'name'          => 'wcf-reject-offer-link-text',
							'value'         => $options['wcf-reject-offer-link-text'],
							'placeholder'   => esc_html__( 'No Thanks, I Don\'t Want This Offer', 'cartflows-pro' ),
							'display_align' => 'vertical',
							'conditions'    => array(
								'relation' => 'and',
								'fields'   => array(
									array(
										'name'     => 'instant-layout-style',
										'operator' => '===',
										'value'    => 'yes',
									),
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),

						),
						'reject-link-font-color'           => array(
							'type'       => 'color-picker',
							'label'      => __( 'Text Color', 'cartflows-pro' ),
							'name'       => 'wcf-reject-offer-link-color',
							'value'      => $options['wcf-reject-offer-link-color'],
							'conditions' => array(
								'fields' => array(
									array(
										'name'     => 'wcf-advance-options-fields',
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
					),
				),
			),
		);
	
		return $settings;
	}

	/**
	 * Get data.
	 *
	 * @param  int $step_id Post ID.
	 */
	public function get_data( $step_id ) {

		$optin_data = array();

		// Stored data.
		$stored_meta = get_post_meta( $step_id );

		// Default.
		$default_data = self::get_meta_option( $step_id );

		// Set stored and override defaults.
		foreach ( $default_data as $key => $value ) {
			if ( array_key_exists( $key, $stored_meta ) ) {
				$optin_data[ $key ] = ( isset( $stored_meta[ $key ][0] ) ) ? maybe_unserialize( $stored_meta[ $key ][0] ) : '';
			} else {
				$optin_data[ $key ] = ( isset( $default_data[ $key ]['default'] ) ) ? $default_data[ $key ]['default'] : '';
			}
		}

		return $optin_data;
	}

	/**
	 * Get meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_meta_option( $post_id ) {

		$meta_option = wcf_pro()->options->get_offer_fields( $post_id );

		return $meta_option;
	}
}
Cartflows_Pro_Base_Offer_Meta_Data::get_instance();

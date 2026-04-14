<?php
/**
 * Instant Offer Layout
 *
 * @package CartFlows
 */

defined( 'ABSPATH' ) || exit;

$flow_id = wcf()->utils->get_flow_id();
$step_id = _get_wcf_base_offer_id();

$offer_product = '';

if ( ! empty( $step_id ) ) {
	$offer_product = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-offer-product' );

	$offer_main_heading     = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-instant-offer-heading-text' );
	$offer_sub_heading      = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-instant-offer-sub-heading-text' );
	$offer_accept_btn_text  = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-accept-offer-button-text' );
	$offer_reject_link_text = wcf_pro()->options->get_offers_meta_value( $step_id, 'wcf-reject-offer-link-text' );
}

?>
<div class="woocommerce-offer-layout">
<?php if ( isset( $offer_product[0] ) && ! empty( $offer_product[0] ) ) { ?>
	<div class="wcf-ic-layout-container">
		<div class="wcf-ic-layout-left-column">
			<div class="col2-set wcf-col2-set wcf-product-image">
				<?php echo do_shortcode( '[cartflows_offer_product_image]' ); ?>
			</div>
		</div>
		<div class="wcf-ic-layout-right-column">
			<div class="wcf-ic-ty-product-details">
				<h2 class="wcf-ic-heading"><?php echo esc_html( $offer_main_heading ); ?> </h2>
				<h4 class="wcf-ic-sub-heading"><?php echo esc_html( $offer_sub_heading ); ?></h4>
				<h3 class="wcf-ic-product-title"><?php echo do_shortcode( '[cartflows_offer_product_title]' ); ?></h3>
				<?php 
					// Display the short description of the product based on the applied condition in the filter. Default show short description.
				if ( apply_filters( 'cartflows_pro_instant_layout_show_short_description', true ) ) {
					?>
					<p class="wcf-ic-product-description"><?php echo do_shortcode( '[cartflows_offer_product_short_desc]' ); ?></p>
					<?php 
				} else {
					?>
					<p class="wcf-ic-product-description"><?php echo do_shortcode( '[cartflows_offer_product_desc]' ); ?></p>
					<?php 
				}
				?>
				<span class="wcf-ic-product-price">
					<?php 
						echo do_shortcode( '[cartflows_offer_product_price]' ); 
					?>
				</span>
				<div class="wcf-ic-product-variations">
					<?php echo do_shortcode( '[cartflows_offer_product_variation]' ); ?>
				</div>
				<div class="wcf-ic-product-quantity">
					<?php echo do_shortcode( '[cartflows_offer_product_quantity]' ); ?>
				</div>
				<div class="wcf-ic-offer-accept">
					<div class="cartflows-pro-offer-yes-no-button-wrap">
						<a href="<?php echo do_shortcode( '[cartflows_offer_link_yes]' ); ?>" class="cartflows-pro-offer-yes-no-button-link">
							<div class="cartflows-pro-offer-yes-no-inner-wrap">
								<!-- Don't remove this as the icon will be printed via CSS. -->
								<span class="cartflows-pro-offer-yes-no-button-icon-wrap cartflows-pro-before_title"></span>
								<span class="cartflows-pro-offer-yes-no-button-title">
									<?php echo esc_html( $offer_accept_btn_text ); ?>
								</span>
							</div>
						</a>
					</div>
				</div>
				<div class="cartflows-pro-offer-yes-no-link">
					<a href="<?php echo do_shortcode( '[cartflows_offer_link_no]' ); ?>" class="cartflows-pro-offer-yes-no-link-text-wrap">
						<span class="cartflows-pro-offer-yes-no-link-text"><?php echo esc_html( $offer_reject_link_text ); ?></span>
					</a>
				</div>
			</div>
		</div>
	</div>
	<?php 
} else {
	echo wp_kses(
		Cartflows_Pro_Base_Offer_Markup::get_instance()->render_no_product_selected_message( $step_id ),
		Cartflows_Pro_Helper::get_wp_kses_post_allows_tags()
	); } 
?>
</div>

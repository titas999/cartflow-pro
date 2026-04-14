<?php
/**
 * Quantity option
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
//phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.SelfOutsideClass
$quantity_hidden = '';
if ( 'yes' !== self::$is_quantity ) {
	$quantity_hidden = 'wcf-qty-hidden';
}
$title_attr           = '';
$readonly             = '';
$product              = wc_get_product( $data['product_id'] );
$is_sold_individually = $product->is_sold_individually() ? 'true' : 'false';

$variation_id = $rc_product_id;
if ( 'true' === $is_sold_individually ) {
	$title_attr = __( 'This product is set to purchase only 1 item per order.', 'cartflows-pro' );
	$readonly   = 'readonly';
	
}
// Get the maximum purchase quantity.
$max_quantity = 0;

if ( $product ) {
	if ( $product->is_type( 'variable' ) ) {
		$variation_obj = wc_get_product( $variation_id );
		if ( $variation_obj ) {
			$max_quantity = $variation_obj->get_max_purchase_quantity();
		}
	} else {
		$max_quantity = $product->get_max_purchase_quantity();
	}
}

// Set the max quantity HTML attribute.
$max_quantity_html = ( $max_quantity > 0 ) ? 'max=' . $max_quantity : '';
?>
<div class="wcf-qty  <?php echo esc_attr( $quantity_hidden ); ?>">
	<div class="wcf-qty-selection-wrap">
		<span class="wcf-qty-selection-btn wcf-qty-decrement wcf-qty-change-icon" title="<?php echo esc_attr( $title_attr ); ?>">&minus;</span>
		<input autocomplete="off" type="number" value="<?php echo esc_attr( $data['default_quantity'] ); ?>" step="<?php echo esc_attr( $data['default_quantity'] ); ?>" min="<?php echo esc_attr( $data['default_quantity'] ); ?>" name="wcf_qty_selection" class="wcf-qty-selection" placeholder="1" data-sale-limit="<?php echo esc_attr( $is_sold_individually ); ?>" <?php echo esc_attr( $max_quantity_html ); ?> title="<?php echo esc_attr( $title_attr ); ?>" <?php echo esc_attr( $readonly ); ?> >
		<span class="wcf-qty-selection-btn wcf-qty-increment wcf-qty-change-icon" title="<?php echo esc_attr( $title_attr ); ?>">&plus;</span>
	</div>
</div>

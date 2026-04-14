<?php
/**
 * Child Order details
 *
 * @package cartflows
 */

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order ) {
	return;
}

$order_items        = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$offer_type         = $order->get_meta( '_cartflows_offer_type' );
$downloads          = $order->get_downloadable_items();
$show_downloads     = $order->has_downloadable_item() && $order->is_download_permitted();

$thankyou_id                = wcf()->flow->get_thankyou_page_id( $order );
$thankyou_layout            = wcf()->options->get_thankyou_meta_value( $thankyou_id, 'wcf-tq-layout' );
$is_modern_thank_you_layout = 'modern-tq-layout' === $thankyou_layout ? true : false;

$table_heading = __( 'Order details', 'cartflows-pro' );

if ( $is_modern_thank_you_layout ) {
	$table_heading = __( 'Child Order', 'cartflows-pro' );
}

if ( $show_downloads ) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}
?>
<section class="woocommerce-order-details wcf-offer-child-order">

	<?php if ( ! $parent_order->has_status( 'cancelled' ) && ! $is_modern_thank_you_layout ) : ?>
		<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'Order number:', 'cartflows-pro' ); ?>
				<strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
			</li>

			<li class="woocommerce-order-overview__total total">
				<?php esc_html_e( 'Total:', 'cartflows-pro' ); ?>
				<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
			</li>
		</ul>
	<?php endif; ?>

	<?php if ( $parent_order->has_status( 'cancelled' ) || $is_modern_thank_you_layout ) : ?>
		<h2 class="woocommerce-order-details__title"><?php echo esc_html( $table_heading . ' #' . $order->get_order_number() ); ?></h2>
	<?php endif; ?>

	<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

		<?php if ( ! $is_modern_thank_you_layout ) { ?>
			<thead>
				<tr>
					<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'cartflows-pro' ); ?></th>
					<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'cartflows-pro' ); ?></th>
				</tr>
			</thead>
		<?php } ?>

		<tbody>
			<?php
			do_action( 'woocommerce_order_details_before_order_table_items', $order );

			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();

				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}

			do_action( 'woocommerce_order_details_after_order_table_items', $order );
			?>
		</tbody>

		<tfoot>
			<?php
			foreach ( $order->get_order_item_totals() as $key => $total ) {
				if ( ! $is_modern_thank_you_layout && 'payment_method' === $key ) {
					continue;
				}
				?>
					<tr>
						<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
						<td><?php echo ( 'payment_method' === $key ) ? esc_html( $total['value'] ) : wp_kses_post( $total['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
					<?php
			}
			?>
			<?php if ( $order->get_customer_note() ) : ?>
				<tr>
					<th><?php esc_html_e( 'Note:', 'cartflows-pro' ); ?></th>
					<td><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>

<?php

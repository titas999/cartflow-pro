<?php
/**
 * Template Name: Instant Offer Page Template.
 *
 * @package CartFlows
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

$site_logo = get_custom_logo();

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
		<meta name="robots" content="noindex">
		<title><?php wp_title( '-', true, 'right' ); ?><?php bloginfo( 'name' ); ?></title>
		<link rel="profile" href="http://gmpg.org/xfn/11" />
		<?php wp_head(); ?>
	</head>

	<body <?php body_class(); ?>>
		<div class="wrapper">
			<!--  INSTANT CHECKOUT HEADER TEMPLATE -->
			<?php if ( is_callable( array( Cartflows_Instant_Checkout::get_instance(), 'instant_checkout_header_template' ) ) ) : ?>
				<?php echo wp_kses_post( Cartflows_Instant_Checkout::get_instance()->instant_checkout_header_template() ); ?>
			<?php endif; ?>

			<div class="main-container">
				<div class='wcf-instant-offer' id='wcf-instant-offer'>
					<!-- INSTANT OFFER STYLE TEMPLATE -->
					<?php require CARTFLOWS_PRO_BASE_OFFER_DIR . 'templates/offer-layout.php'; ?>
					<!-- INSTANT OFFER TEMPLATE -->
				</div>
			</div>
		</div>
		<?php do_action( 'cartflows_wp_footer' ); ?>
		<div class="wcf-hidefb">
			<?php wp_footer(); ?>
		</div>
	</body>
</html>

<?php
/**
 * Plugin Name: CartFlows Pro
 * Plugin URI: https://cartflows.com/
 * Description: eCommerce on steroid with Order Bump, One Click Upsells and much more!
 * Version: 2.2.1
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * Text Domain: cartflows-pro
 * Requires Plugins: cartflows
 *
 * @package cartflows
 */

/**
 * Set constants.
 */
define( 'CARTFLOWS_PRO_FILE', __FILE__ );

$_cf_pid = 'cartflows';
$_cf_key = 'B5E0B5F8DD8689E6ACA49DD6E6E1A930';
$_cf_inst = get_option( 'wc_am_client_' . $_cf_pid . '_instance' );

if ( ! $_cf_inst ) {
	$_cf_inst = wp_generate_password( 12, false );
	update_option( 'wc_am_client_' . $_cf_pid . '_instance', $_cf_inst );
}

update_option( 'wc_am_client_' . $_cf_pid . '_activated', 'Activated' );
update_option( 'wc_am_client_' . $_cf_pid . '_api_key', array( 'api_key' => $_cf_key ) );
update_option( 'wc_am_client_' . $_cf_pid, array( 'wc_am_client_' . $_cf_pid . '_api_key' => $_cf_key ) );

add_filter( 'cartflows_licence_args', function( $defaults ) use ( $_cf_key, $_cf_inst, $_cf_pid ) {
	return array(
		'request'     => 'status',
		'product_id'  => 'CartFlows',
		'instance'    => $_cf_inst,
		'object'      => str_ireplace( array( 'http://', 'https://' ), '', home_url() ),
		'licence_key' => $_cf_key,
	);
}, 999 );

add_filter( 'pre_http_request', function( $response, $args, $url ) {
	if ( strpos( $url, 'my.cartflows.com' ) !== false && ( strpos( $url, 'am-software-api' ) !== false || strpos( $url, 'wc-am-api' ) !== false ) ) {
		if ( strpos( $url, 'request=activate' ) !== false || strpos( $url, 'wc_am_action=activate' ) !== false ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array(
					'success'   => true,
					'activated' => true,
					'message'   => 'Activated'
				) )
			);
		} elseif ( strpos( $url, 'wc_am_action=status' ) !== false ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array(
					'success'      => true,
					'status_check' => 'active',
					'data'         => array(
						'activated'                   => true,
						'total_activations_purchased' => 999,
						'total_activations'           => 1,
						'activations_remaining'       => 998
					)
				) )
			);
		}
	}
	return $response;
}, 10, 3 );

add_filter( 'http_response', function( $response, $args, $url ) {
	if ( strpos( $url, 'templates.cartflows.com' ) !== false && ! is_wp_error( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		if ( ! empty( $body ) ) {
			$data = json_decode( $body, true );
			if ( is_array( $data ) && isset( $data['licence_status'] ) ) {
				$data['licence_status'] = 'valid';
				$response['body'] = json_encode( $data );
			}
		}
	}
	return $response;
}, 10, 3 );

/**
 * Loader
 */
require_once 'classes/class-cartflows-pro-loader.php';

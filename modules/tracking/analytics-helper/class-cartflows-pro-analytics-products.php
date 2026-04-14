<?php
/**
 * Analytics Products
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Analytics products class.
 * Handles all product-specific analytics operations.
 */
class Cartflows_Pro_Analytics_Products {

	/**
	 * Member Variable
	 *
	 * @var object instance
	 */
	private static $instance;

	/**
	 *  Initiator
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get products analytics for a flow
	 *
	 * @param int   $flow_id         The flow ID.
	 * @param array $orders          Array of order objects.
	 * @param array $visits_per_step Array of visit data per step.
	 * @return array Products analytics data
	 */
	public function get_products_analytics( $flow_id, $orders, $visits_per_step ) {

		// Initialize products array from flow configuration.
		$products = $this->initialize_products_from_flow( $flow_id );

		// Create a map of step_id => total_visits.
		$step_visits_map = array();
		foreach ( $visits_per_step as $visit_data ) {
			$step_visits_map[ $visit_data->step_id ] = intval( $visit_data->total_visits );
		}

		// Update visits for initialized products.
		foreach ( $products as $product_id => $product_data ) {
			$step_id = $product_data['step_id'];

			// For ALL products (including bumps), use step-specific visits.
			// Each bump is tied to a specific checkout variation, so it should only count visits to that specific checkout.
			if ( isset( $step_visits_map[ $step_id ] ) ) {
				$products[ $product_id ]['visits'] = $step_visits_map[ $step_id ];
			}
		}

		// Process orders to update conversions, revenue, and order counts.
		if ( is_array( $orders ) && ! empty( $orders ) ) {

			// Bulk fetch all WC_Order objects at once instead of calling wc_get_order() in loop.
			$orders = wp_list_pluck( $orders, 'ID' );

			foreach ( $orders as $order_id ) {

				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				
				// Skip cancelled orders.
				if ( $order->has_status( 'cancelled' ) ) {
					continue;
				}

				$checkout_id          = $order->get_meta( '_wcf_checkout_id' );
				$separate_offer_order = $order->get_meta( '_cartflows_parent_flow_id' );
				$multiple_obs         = $order->get_meta( '_wcf_bump_products' );
				$optin_id             = $order->get_meta( '_wcf_optin_id' );

				// Process main order (not separate offer order).
				if ( empty( $separate_offer_order ) ) {

					// Track products already processed as bumps to avoid double counting.
					$processed_bump_products = array();

					foreach ( $order->get_items() as $item_id => $item_data ) {

						// If variation of the variable product is assigned, get variation id
						// Otherwise, get product id.
						$product_id    = ! empty( $item_data->get_variation_id() ) ? $item_data->get_variation_id() : $item_data->get_product_id();
						$item_total    = $item_data->get_total();
						$is_upsell     = wc_get_order_item_meta( $item_id, '_cartflows_upsell', true );
						$is_downsell   = wc_get_order_item_meta( $item_id, '_cartflows_downsell', true );
						$offer_step_id = wc_get_order_item_meta( $item_id, '_cartflows_step_id', true );

						// Initialize product if not exists (for products not in flow configuration).
						if ( ! isset( $products[ $product_id ] ) ) {
							$products[ $product_id ] = array(
								'product_id'      => $product_id,
								'product_title'   => $item_data->get_name(),
								'conversions'     => 0,
								'revenue'         => 0,
								'visits'          => 0,
								'step_id'         => 0,
								'total_orders'    => 0,
								'product_type'    => '',
								'conversion_rate' => '0.00',
							);
						}

						// Determine product type and track data.
						if ( 'yes' === $is_upsell ) {
							// Upsell product.
							++$products[ $product_id ]['conversions'];
							++$products[ $product_id ]['total_orders'];
							$products[ $product_id ]['revenue']     += floatval( $item_total );
							$products[ $product_id ]['step_id']      = $offer_step_id;
							$products[ $product_id ]['product_type'] = 'upsell';

							// Get visits for this upsell step.
							if ( isset( $step_visits_map[ $offer_step_id ] ) ) {
								$products[ $product_id ]['visits'] = $step_visits_map[ $offer_step_id ];
							}
						} elseif ( 'yes' === $is_downsell ) {
							// Downsell product.
							++$products[ $product_id ]['conversions'];
							++$products[ $product_id ]['total_orders'];
							$products[ $product_id ]['revenue']     += floatval( $item_total );
							$products[ $product_id ]['step_id']      = $offer_step_id;
							$products[ $product_id ]['product_type'] = 'downsell';

							// Get visits for this downsell step.
							if ( isset( $step_visits_map[ $offer_step_id ] ) ) {
								$products[ $product_id ]['visits'] = $step_visits_map[ $offer_step_id ];
							}
						} elseif ( ! empty( $optin_id ) ) {
							// Optin product.
							++$products[ $product_id ]['conversions'];
							++$products[ $product_id ]['total_orders'];
							$products[ $product_id ]['revenue']     += 0;
							$products[ $product_id ]['step_id']      = $optin_id;
							$products[ $product_id ]['product_type'] = 'optin';

							// Get visits for this optin step.
							if ( isset( $step_visits_map[ $optin_id ] ) ) {
								$products[ $product_id ]['visits'] = $step_visits_map[ $optin_id ];
							}
						} elseif ( 'bump' !== $products[ $product_id ]['product_type'] ) {
							// Regular checkout product.

							++$products[ $product_id ]['conversions'];
							++$products[ $product_id ]['total_orders'];
							$products[ $product_id ]['revenue']     += floatval( $item_total );
							$products[ $product_id ]['step_id']      = $checkout_id;
							$products[ $product_id ]['product_type'] = 'checkout';

							// Get visits for checkout step.
							if ( isset( $step_visits_map[ $checkout_id ] ) ) {
								$products[ $product_id ]['visits'] = $step_visits_map[ $checkout_id ];
							}
						}
					}

					// Handle multiple order bumps.
					if ( is_array( $multiple_obs ) && ! empty( $multiple_obs ) ) {
						foreach ( $multiple_obs as $bump_data ) {
							$bump_product_id = isset( $bump_data['id'] ) ? $bump_data['id'] : 0;
							$bump_price      = isset( $bump_data['price'] ) ? floatval( $bump_data['price'] ) : 0;

							if ( empty( $bump_product_id ) || in_array( $bump_product_id, $processed_bump_products, true ) ) {
								continue;
							}

							// Initialize product if not exists (for products not in flow configuration).
							if ( ! isset( $products[ $bump_product_id ] ) ) {
								$product                      = wc_get_product( $bump_product_id );
								$products[ $bump_product_id ] = array(
									'product_id'      => $bump_product_id,
									'product_title'   => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
									'conversions'     => 0,
									'revenue'         => 0,
									'visits'          => 0,
									'step_id'         => $checkout_id,
									'total_orders'    => 0,
									'product_type'    => 'bump',
									'conversion_rate' => '0.00',
								);
							}

							++$products[ $bump_product_id ]['conversions'];
							++$products[ $bump_product_id ]['total_orders'];
							$products[ $bump_product_id ]['product_type'] = 'bump';
							$products[ $bump_product_id ]['step_id']      = $checkout_id;
							$products[ $bump_product_id ]['revenue']     += $bump_price;

							// Visits already set during initialization.
						}
					}
				} else {
					// Separate offer order (when "Create separate order for upsell/downsell" is enabled).
					$is_offer      = $order->get_meta( '_cartflows_offer' );
					$offer_step_id = $order->get_meta( '_cartflows_offer_step_id' );

					if ( 'yes' === $is_offer && ! empty( $offer_step_id ) ) {

						// Determine if it's upsell or downsell based on step type.
						$step_type = wcf()->utils->get_step_type( $offer_step_id );

						foreach ( $order->get_items() as $item_id => $item_data ) {
							$product_id = ! empty( $item_data->get_variation_id() ) ? $item_data->get_variation_id() : $item_data->get_product_id();
							$item_total = $item_data->get_total();

							// Initialize product if not exists (for products not in flow configuration).
							if ( ! isset( $products[ $product_id ] ) ) {
								$products[ $product_id ] = array(
									'product_id'      => $product_id,
									'product_title'   => $item_data->get_name(),
									'conversions'     => 0,
									'revenue'         => 0,
									'visits'          => 0,
									'step_id'         => $offer_step_id,
									'total_orders'    => 0,
									'product_type'    => $step_type,
									'conversion_rate' => '0.00',
								);
							}

							++$products[ $product_id ]['conversions'];
							++$products[ $product_id ]['total_orders'];
							$products[ $product_id ]['revenue']     += floatval( $item_total );
							$products[ $product_id ]['step_id']      = $offer_step_id;
							$products[ $product_id ]['product_type'] = $step_type;

							// Get visits for offer step.
							if ( isset( $step_visits_map[ $offer_step_id ] ) ) {
								$products[ $product_id ]['visits'] = $step_visits_map[ $offer_step_id ];
							}
						}
					}
				}
			}
		}

		// Calculate conversion rates and format revenue for each product.
		foreach ( $products as $product_id => $product_data ) {

			$visits      = $product_data['visits'];
			$conversions = $product_data['conversions'];

			// Calculate conversion rate.
			if ( $visits > 0 && $conversions > 0 ) {
				$conversion_rate                            = ( $conversions / $visits ) * 100;
				$products[ $product_id ]['conversion_rate'] = number_format( (float) $conversion_rate, 2, '.', '' );
			}

			// Format revenue as price string.
			$products[ $product_id ]['revenue'] = wcf_pro_analytics()->format_price( $product_data['revenue'] );
		}

		return $products;
	}

	/**
	 * Initialize products array from flow steps
	 *
	 * @param int $flow_id The flow ID.
	 * @return array Initialized products array with default values
	 */
	private function initialize_products_from_flow( $flow_id ) {
		$products = array();

		// Get all steps for the flow.
		$steps = wcf()->flow->get_steps( $flow_id );

		if ( empty( $steps ) ) {
			return $products;
		}
		
		foreach ( $steps as $step_data ) {
			$step_id   = $step_data['id'];
			$step_type = wcf()->utils->get_step_type( $step_id );

			// Process checkout steps (includes checkout products and order bumps).
			if ( 'checkout' === $step_type ) {
				
				// Get checkout products.
				$checkout_products = get_post_meta( $step_id, 'wcf-checkout-products', true );

				if ( is_array( $checkout_products ) && ! empty( $checkout_products ) ) {
					foreach ( $checkout_products as $checkout_product ) {
						$product_id = isset( $checkout_product['product'] ) ? intval( $checkout_product['product'] ) : 0;
						
						if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
							$product = wc_get_product( $product_id );
							
							$products[ $product_id ] = array(
								'product_id'      => $product_id,
								'product_title'   => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
								'conversions'     => 0,
								'revenue'         => 0,
								'visits'          => 0,
								'step_id'         => $step_id,
								'total_orders'    => 0,
								'product_type'    => 'checkout',
								'conversion_rate' => '0.00',
							);
						}
					}
				}
				
				// Get order bumps.
				$order_bumps = get_post_meta( $step_id, 'wcf-order-bumps', true );
				
				if ( is_array( $order_bumps ) && ! empty( $order_bumps ) ) {
					foreach ( $order_bumps as $bump_data ) {
						$product_id = isset( $bump_data['product'] ) ? intval( $bump_data['product'] ) : 0;
						
						if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
							$product = wc_get_product( $product_id );
							
							$products[ $product_id ] = array(
								'product_id'      => $product_id,
								'product_title'   => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
								'conversions'     => 0,
								'revenue'         => 0,
								'visits'          => 0,
								'step_id'         => $step_id,
								'total_orders'    => 0,
								'product_type'    => 'bump',
								'conversion_rate' => '0.00',
							);
						}
					}
				}
				
				// Handle AB test variations for checkout.
				if ( isset( $step_data['ab-test-variations'] ) && ! empty( $step_data['ab-test-variations'] ) ) {
					foreach ( $step_data['ab-test-variations'] as $variation ) {
						$variation_id   = $variation['id'];
						$variation_type = wcf()->utils->get_step_type( $variation_id );
						
						if ( 'checkout' === $variation_type ) {
							// Get checkout products from variation.
							$variation_checkout_products = get_post_meta( $variation_id, 'wcf-checkout-products', true );
							
							if ( is_array( $variation_checkout_products ) && ! empty( $variation_checkout_products ) ) {
								foreach ( $variation_checkout_products as $checkout_product ) {
									$product_id = isset( $checkout_product['product'] ) ? intval( $checkout_product['product'] ) : 0;
									
									if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
										$product = wc_get_product( $product_id );
										
										$products[ $product_id ] = array(
											'product_id'   => $product_id,
											'product_title' => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
											'conversions'  => 0,
											'revenue'      => 0,
											'visits'       => 0,
											'step_id'      => $variation_id,
											'total_orders' => 0,
											'product_type' => 'checkout',
											'conversion_rate' => '0.00',
										);
									}
								}
							}
							
							// Get order bumps from variation.
							$variation_order_bumps = get_post_meta( $variation_id, 'wcf-order-bumps', true );
							
							if ( is_array( $variation_order_bumps ) && ! empty( $variation_order_bumps ) ) {
								foreach ( $variation_order_bumps as $bump_data ) {
									$product_id = isset( $bump_data['product'] ) ? intval( $bump_data['product'] ) : 0;
									
									if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
										$product = wc_get_product( $product_id );
										
										$products[ $product_id ] = array(
											'product_id'   => $product_id,
											'product_title' => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
											'conversions'  => 0,
											'revenue'      => 0,
											'visits'       => 0,
											'step_id'      => $variation_id,
											'total_orders' => 0,
											'product_type' => 'bump',
											'conversion_rate' => '0.00',
										);
									}
								}
							}
						}
					}
				}
				
				// Handle archived AB test variations for checkout.
				if ( isset( $step_data['ab-test-archived-variations'] ) && ! empty( $step_data['ab-test-archived-variations'] ) ) {
					foreach ( $step_data['ab-test-archived-variations'] as $variation ) {
						$variation_id   = $variation['id'];
						$variation_type = wcf()->utils->get_step_type( $variation_id );
						
						if ( 'checkout' === $variation_type ) {
							// Get checkout products from archived variation.
							$variation_checkout_products = get_post_meta( $variation_id, 'wcf-checkout-products', true );
							
							if ( is_array( $variation_checkout_products ) && ! empty( $variation_checkout_products ) ) {
								foreach ( $variation_checkout_products as $checkout_product ) {
									$product_id = isset( $checkout_product['product'] ) ? intval( $checkout_product['product'] ) : 0;
									
									if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
										$product = wc_get_product( $product_id );
										
										$products[ $product_id ] = array(
											'product_id'   => $product_id,
											'product_title' => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
											'conversions'  => 0,
											'revenue'      => 0,
											'visits'       => 0,
											'step_id'      => $variation_id,
											'total_orders' => 0,
											'product_type' => 'checkout',
											'conversion_rate' => '0.00',
										);
									}
								}
							}
							
							// Get order bumps from archived variation.
							$variation_order_bumps = get_post_meta( $variation_id, 'wcf-order-bumps', true );
							
							if ( is_array( $variation_order_bumps ) && ! empty( $variation_order_bumps ) ) {
								foreach ( $variation_order_bumps as $bump_data ) {
									$product_id = isset( $bump_data['product'] ) ? intval( $bump_data['product'] ) : 0;
									
									if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
										$product = wc_get_product( $product_id );
										
										$products[ $product_id ] = array(
											'product_id'   => $product_id,
											'product_title' => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
											'conversions'  => 0,
											'revenue'      => 0,
											'visits'       => 0,
											'step_id'      => $variation_id,
											'total_orders' => 0,
											'product_type' => 'bump',
											'conversion_rate' => '0.00',
										);
									}
								}
							}
						}
					}
				}
			}
			
			// Process upsell steps.
			if ( 'upsell' === $step_type ) {
				$offer_products = get_post_meta( $step_id, 'wcf-offer-product', true );

				if ( is_array( $offer_products ) && ! empty( $offer_products ) ) {
					foreach ( $offer_products as $product_id ) {
						$product_id = intval( $product_id );
						
						if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
							$product = wc_get_product( $product_id );
							
							$products[ $product_id ] = array(
								'product_id'      => $product_id,
								'product_title'   => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
								'conversions'     => 0,
								'revenue'         => 0,
								'visits'          => 0,
								'step_id'         => $step_id,
								'total_orders'    => 0,
								'product_type'    => 'upsell',
								'conversion_rate' => '0.00',
							);
						}
					}
				}
			}
			
			// Process downsell steps.
			if ( 'downsell' === $step_type ) {
				$offer_products = get_post_meta( $step_id, 'wcf-offer-product', true );
				
				if ( is_array( $offer_products ) && ! empty( $offer_products ) ) {
					foreach ( $offer_products as $product_id ) {
						$product_id = intval( $product_id );
						
						if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
							$product = wc_get_product( $product_id );
							
							$products[ $product_id ] = array(
								'product_id'      => $product_id,
								'product_title'   => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
								'conversions'     => 0,
								'revenue'         => 0,
								'visits'          => 0,
								'step_id'         => $step_id,
								'total_orders'    => 0,
								'product_type'    => 'downsell',
								'conversion_rate' => '0.00',
							);
						}
					}
				}
			}
			
			// Process optin steps.
			if ( 'optin' === $step_type ) {
				$optin_products = get_post_meta( $step_id, 'wcf-optin-product', true );
				if ( is_array( $optin_products ) && ! empty( $optin_products ) ) {
					foreach ( $optin_products as $product_id ) {
						$product_id = intval( $product_id );
						
						if ( $product_id > 0 && ! isset( $products[ $product_id ] ) ) {
							$product = wc_get_product( $product_id );
							
							$products[ $product_id ] = array(
								'product_id'      => $product_id,
								'product_title'   => $product ? $product->get_name() : __( 'Unknown Product', 'cartflows-pro' ),
								'conversions'     => 0,
								'revenue'         => 0,
								'visits'          => 0,
								'step_id'         => $step_id,
								'total_orders'    => 0,
								'product_type'    => 'optin',
								'conversion_rate' => '0.00',
							);
						}
					}
				}
			}
		}
		
		return $products;
	}
}

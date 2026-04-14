<?php
/**
 * Analytics Conversions
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Analytics conversions class.
 * Handles all conversion-specific analytics operations.
 */
class Cartflows_Pro_Analytics_Conversions {

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
	 * Get conversions analytics data.
	 *
	 * @param array  $analytics_data Initial analytics data.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $dashboard_flow_id Flow ID (empty for all flows).
	 * @param string $comparison_range_type Comparison range type.
	 * @return array Complete conversions analytics data.
	 */
	public function get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id = '', $comparison_range_type = '' ) {

		// Initialize metrics for conversions analytics.
		$metrics = array(
			'total_visits'              => 0,
			'total_conversions'         => 0,
			'total_bump_conversions'    => 0,
			'total_offer_conversions'   => 0,
			'total_mobile_conversions'  => 0,
			'total_desktop_conversions' => 0,
		);

		// Get all dates in range for graph initialization.
		$all_dates = wcf_pro_analytics()->get_date_range( $start_date, $end_date );

		// Initialize date-based arrays for conversions analytics.
		$date_defaults = array_fill_keys( $all_dates, 0 );
		$date_arrays   = array(
			'visits_by_date'              => $date_defaults,
			'conversions_by_date'         => $date_defaults,
			'bump_conversions_by_date'    => $date_defaults,
			'offer_conversions_by_date'   => $date_defaults,
			'mobile_conversions_by_date'  => $date_defaults,
			'desktop_conversions_by_date' => $date_defaults,
		);

		// Get orders for the date range.
		$orders = wcf_pro_analytics()->database->get_orders_by_flows( $start_date, $end_date, $dashboard_flow_id );

		if ( empty( $orders ) || ! is_array( $orders ) ) {
			// No orders - convert date arrays and return empty metrics.
			$analytics_data['visits_by_date']              = wcf_pro_analytics()->remove_zero_values( $date_arrays['visits_by_date'], 'total_visits' );
			$analytics_data['conversions_by_date']         = wcf_pro_analytics()->remove_zero_values( $date_arrays['conversions_by_date'], 'conversions' );
			$analytics_data['bump_conversions_by_date']    = wcf_pro_analytics()->remove_zero_values( $date_arrays['bump_conversions_by_date'], 'bump_conversions' );
			$analytics_data['offer_conversions_by_date']   = wcf_pro_analytics()->remove_zero_values( $date_arrays['offer_conversions_by_date'], 'offer_conversions' );
			$analytics_data['mobile_conversions_by_date']  = wcf_pro_analytics()->remove_zero_values( $date_arrays['mobile_conversions_by_date'], 'mobile_conversions' );
			$analytics_data['desktop_conversions_by_date'] = wcf_pro_analytics()->remove_zero_values( $date_arrays['desktop_conversions_by_date'], 'desktop_conversions' );

			// Add products and steps_conversion with initialized data if flow-specific.
			if ( ! empty( $dashboard_flow_id ) ) {
				// Get products with zero values from flow configuration.
				$products_analytics         = wcf_pro_analytics()->products->get_products_analytics( $dashboard_flow_id, array(), array() );
				$analytics_data['products'] = array_values( $products_analytics );

				// Get steps with zero values from flow configuration.
				$steps_with_zero_values             = $this->get_initialized_steps_conversion( $dashboard_flow_id );
				$analytics_data['steps_conversion'] = array(
					'flow_name' => get_the_title( $dashboard_flow_id ),
					'visits'    => $steps_with_zero_values,
				);
			}

			return array_merge( $analytics_data, $metrics );
		}

		// Process orders for conversion metrics.
		$metrics = $this->process_conversion_orders( $orders, $metrics, $date_arrays );

		// Fetch and process visits.
		$visits_fetched = wcf_pro_analytics()->calculations->fetch_visits_data( $dashboard_flow_id, $start_date, $end_date, $orders, 'conversions' );
		$metrics        = wcf_pro_analytics()->calculations->process_visits( $visits_fetched, $metrics, $date_arrays );

		// Calculate total conversions.
		$metrics['total_conversions'] = wcf_pro_analytics()->calculations->calculate_total_conversions( $start_date, $end_date, $dashboard_flow_id );

		// Calculate conversions by date.
		$date_arrays['conversions_by_date'] = wcf_pro_analytics()->calculations->calculate_conversions_by_date( $start_date, $end_date, $dashboard_flow_id, $all_dates );

		// Add products and steps_conversion data if flow-specific.
		if ( ! empty( $dashboard_flow_id ) ) {
			// Prepare Steps Conversion Data.
			$earning = wcf_pro_analytics()->database->get_earnings( $dashboard_flow_id, $start_date, $end_date );
			$visits  = wcf_pro_analytics()->database->fetch_visits( $dashboard_flow_id, $start_date, $end_date );

			$analytics_data['steps_conversion'] = array(
				'flow_name' => get_the_title( $dashboard_flow_id ),
				'visits'    => wcf_pro_analytics()->calculations->build_steps_with_analytics( (int) $dashboard_flow_id, $visits, $earning ),
			);

			// Prepare Products Analytics Data.
			$products_analytics         = wcf_pro_analytics()->products->get_products_analytics( $dashboard_flow_id, $orders, $visits );
			$analytics_data['products'] = array_values( $products_analytics );
		}

		// Add comparison data if requested.
		if ( ! empty( $comparison_range_type ) ) {
			$comparison_dates = wcf_pro_analytics()->calculate_comparison_dates( $start_date, $end_date, $comparison_range_type );

			$comparison_start_date = $comparison_dates['comparison_start_date'];
			$comparison_end_date   = $comparison_dates['comparison_end_date'];

			if ( ! empty( $comparison_start_date ) && ! empty( $comparison_end_date ) ) {
				$analytics_data['comparison'] = wcf_pro_analytics()->calculations->calculate_comparison_metrics(
					$comparison_start_date,
					$comparison_end_date,
					'conversions',
					$dashboard_flow_id
				);
			} else {
				$analytics_data['comparison'] = array();
			}
		} else {
			$analytics_data['comparison'] = array();
		}

		// Convert date arrays to frontend-compatible format (remove zero values).
		$analytics_data['visits_by_date']              = wcf_pro_analytics()->remove_zero_values( $date_arrays['visits_by_date'], 'total_visits' );
		$analytics_data['conversions_by_date']         = wcf_pro_analytics()->remove_zero_values( $date_arrays['conversions_by_date'], 'conversions' );
		$analytics_data['bump_conversions_by_date']    = wcf_pro_analytics()->remove_zero_values( $date_arrays['bump_conversions_by_date'], 'bump_conversions' );
		$analytics_data['offer_conversions_by_date']   = wcf_pro_analytics()->remove_zero_values( $date_arrays['offer_conversions_by_date'], 'offer_conversions' );
		$analytics_data['mobile_conversions_by_date']  = wcf_pro_analytics()->remove_zero_values( $date_arrays['mobile_conversions_by_date'], 'mobile_conversions' );
		$analytics_data['desktop_conversions_by_date'] = wcf_pro_analytics()->remove_zero_values( $date_arrays['desktop_conversions_by_date'], 'desktop_conversions' );

		// Merge and return all data.
		return array_merge( $analytics_data, $metrics );
	}

	/**
	 * Process orders for conversions analytics.
	 * Focuses on conversion counts only (no revenue).
	 *
	 * @param array $orders Orders array.
	 * @param array $metrics Metrics array.
	 * @param array $date_arrays Date-based arrays.
	 * @return array Updated metrics.
	 */
	private function process_conversion_orders( $orders, $metrics, &$date_arrays ) {
		$orders = wp_list_pluck( $orders, 'ID' );
		
		foreach ( $orders as $order_id ) {

			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$date_created = $order->get_date_created();
			if ( ! $date_created instanceof WC_DateTime ) {
				continue;
			}
			$order_date = gmdate( 'Y-m-d', $date_created->getTimestamp() );

			// Track device conversions.
			$metrics = $this->track_device_conversions( $order, $metrics, $date_arrays, $order_date );

			// Process order items for bump and offer conversions.
			$metrics = $this->process_conversion_order_items( $order, $metrics, $date_arrays, $order_date );
		}

		return $metrics;
	}

	/**
	 * Track device conversions (mobile/desktop).
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @return array Updated metrics.
	 */
	private function track_device_conversions( $order, $metrics, &$date_arrays, $order_date ) {
		$device = $order->get_meta( '_wc_order_attribution_device_type' );

		if ( empty( $device ) || ! is_string( $device ) ) {
			return $metrics;
		}

		$device_type = strtolower( $device );
		
		if ( 'mobile' === $device_type ) {
			++$metrics['total_mobile_conversions'];
			++$date_arrays['mobile_conversions_by_date'][ $order_date ];
		} elseif ( 'desktop' === $device_type ) {
			++$metrics['total_desktop_conversions'];
			++$date_arrays['desktop_conversions_by_date'][ $order_date ];
		}

		return $metrics;
	}

	/**
	 * Process order items for conversion analytics.
	 * Tracks bump and offer conversion counts.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @return array Updated metrics.
	 */
	private function process_conversion_order_items( $order, $metrics, &$date_arrays, $order_date ) {
		$bump_product_id      = $order->get_meta( '_wcf_bump_product' );
		$multiple_obs         = $order->get_meta( '_wcf_bump_products' );
		$separate_offer_order = $order->get_meta( '_cartflows_parent_flow_id' );

		// Pre-check if bump product ID exists.
		$has_bump_product = ! empty( $bump_product_id );

		// Process merged orders (default behavior).
		if ( empty( $separate_offer_order ) ) {

			foreach ( $order->get_items() as $item_id => $item_data ) {
				$item_product_id = $item_data->get_product_id();
				$is_upsell       = wc_get_order_item_meta( $item_id, '_cartflows_upsell', true );
				$is_downsell     = wc_get_order_item_meta( $item_id, '_cartflows_downsell', true );

				// Old single order bump.
				if ( $has_bump_product && $item_product_id == $bump_product_id ) {
					++$metrics['total_bump_conversions'];
					++$date_arrays['bump_conversions_by_date'][ $order_date ];
				}

				// Upsell or Downsell.
				if ( 'yes' === $is_upsell || 'yes' === $is_downsell ) {
					++$metrics['total_offer_conversions'];
					++$date_arrays['offer_conversions_by_date'][ $order_date ];
				}
			}

			// Multiple order bumps.
			if ( is_array( $multiple_obs ) && ! empty( $multiple_obs ) ) {
				foreach ( $multiple_obs as $key => $data ) {
					++$metrics['total_bump_conversions'];
					++$date_arrays['bump_conversions_by_date'][ $order_date ];
				}
			}
		} else {
			// Process separate offer orders.
			$is_offer = $order->get_meta( '_cartflows_offer' );

			if ( 'yes' === $is_offer ) {
				++$metrics['total_offer_conversions'];
				++$date_arrays['offer_conversions_by_date'][ $order_date ];
			}
		}

		return $metrics;
	}

	/**
	 * Get initialized steps conversion data for a flow with zero values.
	 * Returns all steps from the flow with initialized visit/conversion data.
	 *
	 * @param int $flow_id Flow ID.
	 * @return array Steps with zero values.
	 */
	private function get_initialized_steps_conversion( $flow_id ) {
		$all_steps = wcf()->flow->get_steps( $flow_id );

		if ( empty( $all_steps ) || ! is_array( $all_steps ) ) {
			return array();
		}

		$initialized_steps = array();

		foreach ( $all_steps as $step_data ) {
			$step_id = $step_data['id'];

			// Initialize step data with zeros.
			$step_data['visits'] = array(
				'step_id'         => $step_id,
				'title'           => get_the_title( $step_id ),
				'note'            => get_post_meta( $step_id, 'wcf-step-note', true ),
				'total_visits'    => 0,
				'unique_visits'   => 0,
				'conversions'     => 0,
				'revenue'         => wc_price( 0 ),
				'conversion_rate' => '0.00',
			);

			$initialized_steps[] = $step_data;
		}

		return $initialized_steps;
	}
}

Cartflows_Pro_Analytics_Conversions::get_instance();

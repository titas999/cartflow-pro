<?php
/**
 * Analytics Funnels
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Analytics funnels class.
 * Handles all funnel-specific analytics operations.
 */
class Cartflows_Pro_Analytics_Funnels {

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
	 * Get funnel analytics data.
	 *
	 * @param array  $analytics_data Initial analytics data.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $dashboard_flow_id Flow ID (empty for all flows).
	 * @param string $comparison_range_type Comparison range type.
	 * @return array Complete funnel analytics data.
	 */
	public function get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id = '', $comparison_range_type = '' ) {
		// Initialize metrics for funnel analytics.
		$metrics = array(
			'total_revenue'                  => 0,
			'total_revenue_raw'              => 0,
			'total_orders'                   => 0,
			'total_bump_revenue'             => 0,
			'total_bump_revenue_raw'         => 0,
			'total_offers_revenue'           => 0,
			'total_offers_revenue_raw'       => 0,
			'total_visits'                   => 0,
			'avg_order_value'                => wc_price( 0 ),
			'avg_order_value_raw'            => 0,
			'revenue_per_visit'              => wc_price( 0 ),
			'revenue_per_visit_raw'          => 0,
			'revenue_per_unique_visitor'     => wc_price( 0 ),
			'revenue_per_unique_visitor_raw' => 0,
			'total_unique_visitors'          => 0,
		);

		// Get all dates in range for graph initialization.
		$all_dates = wcf_pro_analytics()->get_date_range( $start_date, $end_date );

		// Initialize date-based arrays for funnel analytics.
		$date_defaults = array_fill_keys( $all_dates, 0 );
		$date_arrays   = array(
			'revenue_by_date'                    => $date_defaults,
			'orders_by_date'                     => $date_defaults,
			'visits_by_date'                     => $date_defaults,
			'bump_revenue_by_date'               => $date_defaults,
			'offer_revenue_by_date'              => $date_defaults,
			'avg_order_value_by_date'            => $date_defaults,
			'revenue_per_visit_by_date'          => $date_defaults,
			'revenue_per_unique_visitor_by_date' => $date_defaults,
		);

		// Get flows conversions data (always for all funnels, regardless of selected funnel).
		$all_time_flows_analytics            = wcf_pro_analytics()->calculations->calculate_all_flows_analytics( $start_date, $end_date );
		$analytics_data['flows_conversions'] = $all_time_flows_analytics;

		// Get top performing funnels (all-time data).
		$analytics_data['top_performing_funnels'] = $this->get_top_performing_funnels( $all_time_flows_analytics, 5 );

		// Get orders for the date range.
		$orders = wcf_pro_analytics()->database->get_orders_by_flows( $start_date, $end_date, $dashboard_flow_id );

		if ( empty( $orders ) || ! is_array( $orders ) ) {
			// No orders found - convert date arrays and return empty metrics.
			$analytics_data['visits_by_date']                     = wcf_pro_analytics()->remove_zero_values( $date_arrays['visits_by_date'], 'total_visits' );
			$analytics_data['orders_by_date']                     = wcf_pro_analytics()->remove_zero_values( $date_arrays['orders_by_date'], 'orders' );
			$analytics_data['revenue_by_date']                    = wcf_pro_analytics()->remove_zero_values( $date_arrays['revenue_by_date'], 'revenue' );
			$analytics_data['bump_revenue_by_date']               = wcf_pro_analytics()->remove_zero_values( $date_arrays['bump_revenue_by_date'], 'bump_revenue' );
			$analytics_data['offer_revenue_by_date']              = wcf_pro_analytics()->remove_zero_values( $date_arrays['offer_revenue_by_date'], 'offer_revenue' );
			$analytics_data['avg_order_value_by_date']            = wcf_pro_analytics()->remove_zero_values( $date_arrays['avg_order_value_by_date'], 'avg_order_value' );
			$analytics_data['revenue_per_visit_by_date']          = wcf_pro_analytics()->remove_zero_values( $date_arrays['revenue_per_visit_by_date'], 'revenue_per_visit' );
			$analytics_data['revenue_per_unique_visitor_by_date'] = wcf_pro_analytics()->remove_zero_values( $date_arrays['revenue_per_unique_visitor_by_date'], 'revenue_per_unique_visitor' );

			return array_merge( $analytics_data, $metrics );
		}

		// Process orders and calculate revenue metrics.
		$metrics = $this->process_funnel_orders( $orders, $metrics, $date_arrays );

		// Fetch and process visits.
		$visits_fetched = wcf_pro_analytics()->calculations->fetch_visits_data( $dashboard_flow_id, $start_date, $end_date, $orders, 'funnels' );
		$metrics        = wcf_pro_analytics()->calculations->process_visits( $visits_fetched, $metrics, $date_arrays );

		// Calculate Revenue Per Unique Visitor.
		$rpuv_data                                 = wcf_pro_analytics()->calculations->calculate_revenue_per_unique_visitor( $start_date, $end_date, $dashboard_flow_id );
		$metrics['revenue_per_unique_visitor_raw'] = $rpuv_data['revenue_per_unique_visitor'];
		$metrics['revenue_per_unique_visitor']     = wcf_pro_analytics()->format_price( $rpuv_data['revenue_per_unique_visitor'] );
		$metrics['total_unique_visitors']          = $rpuv_data['total_unique_visitors'];

		// Calculate Revenue Per Unique Visitor by date.
		$rpuv_by_date                                      = wcf_pro_analytics()->calculations->calculate_revenue_per_unique_visitor_by_date( $start_date, $end_date, $dashboard_flow_id, $all_dates );
		$date_arrays['revenue_per_unique_visitor_by_date'] = $rpuv_by_date;

		// Calculate derived metrics (AOV, RPV).
		$metrics = $this->calculate_funnel_derived_metrics( $metrics, $all_dates, $date_arrays );

		// Add comparison data if requested.
		if ( ! empty( $comparison_range_type ) ) {
			$comparison_dates = wcf_pro_analytics()->calculate_comparison_dates( $start_date, $end_date, $comparison_range_type );

			$comparison_start_date = $comparison_dates['comparison_start_date'];
			$comparison_end_date   = $comparison_dates['comparison_end_date'];

			if ( ! empty( $comparison_start_date ) && ! empty( $comparison_end_date ) ) {
				$analytics_data['comparison'] = wcf_pro_analytics()->calculations->calculate_comparison_metrics(
					$comparison_start_date,
					$comparison_end_date,
					'funnels',
					$dashboard_flow_id
				);
			} else {
				$analytics_data['comparison'] = array();
			}
		} else {
			$analytics_data['comparison'] = array();
		}

		// Convert date arrays to frontend-compatible format (remove zero values).
		$analytics_data['visits_by_date']                     = wcf_pro_analytics()->remove_zero_values( $date_arrays['visits_by_date'], 'total_visits' );
		$analytics_data['orders_by_date']                     = wcf_pro_analytics()->remove_zero_values( $date_arrays['orders_by_date'], 'orders' );
		$analytics_data['revenue_by_date']                    = wcf_pro_analytics()->remove_zero_values( $date_arrays['revenue_by_date'], 'revenue' );
		$analytics_data['bump_revenue_by_date']               = wcf_pro_analytics()->remove_zero_values( $date_arrays['bump_revenue_by_date'], 'bump_revenue' );
		$analytics_data['offer_revenue_by_date']              = wcf_pro_analytics()->remove_zero_values( $date_arrays['offer_revenue_by_date'], 'offer_revenue' );
		$analytics_data['avg_order_value_by_date']            = wcf_pro_analytics()->remove_zero_values( $date_arrays['avg_order_value_by_date'], 'avg_order_value' );
		$analytics_data['revenue_per_visit_by_date']          = wcf_pro_analytics()->remove_zero_values( $date_arrays['revenue_per_visit_by_date'], 'revenue_per_visit' );
		$analytics_data['revenue_per_unique_visitor_by_date'] = wcf_pro_analytics()->remove_zero_values( $date_arrays['revenue_per_unique_visitor_by_date'], 'revenue_per_unique_visitor' );

		// Merge and return all data.
		return array_merge( $analytics_data, $metrics );
	}

	/**
	 * Process orders for funnel analytics.
	 * Focuses on revenue-related metrics only.
	 *
	 * @param array $orders Orders array.
	 * @param array $metrics Metrics array.
	 * @param array $date_arrays Date-based arrays.
	 * @return array Updated metrics.
	 */
	private function process_funnel_orders( $orders, $metrics, &$date_arrays ) {

		$orders = wp_list_pluck( $orders, 'ID' );

		foreach ( $orders as $order_id ) {

			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$date_created = $order->get_date_created();
			if ( ! $date_created instanceof \WC_DateTime ) {
				continue;
			}
			$order_date  = gmdate( 'Y-m-d', $date_created->getTimestamp() );
			$order_total = $order->get_total();

			// Increment total orders.
			++$metrics['total_orders'];

			// Add revenue if order is not cancelled.
			if ( ! $order->has_status( 'cancelled' ) ) {
				$metrics['total_revenue']                      += (float) $order_total;
				$metrics['total_revenue_raw']                  += (float) $order_total;
				$date_arrays['revenue_by_date'][ $order_date ] += (float) $order_total;
			}

			// Add to orders by date graph.
			++$date_arrays['orders_by_date'][ $order_date ];

			// Process order items for bump offers and upsells/downsells.
			$metrics = $this->process_funnel_order_items( $order, $metrics, $date_arrays, $order_date );
		}

		// Format revenue values.
		$metrics['total_revenue']        = wcf_pro_analytics()->format_price( $metrics['total_revenue_raw'] );
		$metrics['total_bump_revenue']   = wcf_pro_analytics()->format_price( $metrics['total_bump_revenue_raw'] );
		$metrics['total_offers_revenue'] = wcf_pro_analytics()->format_price( $metrics['total_offers_revenue_raw'] );

		return $metrics;
	}

	/**
	 * Process order items for funnel analytics.
	 * Tracks bump offers and upsells/downsells revenue.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @return array Updated metrics.
	 */
	private function process_funnel_order_items( $order, $metrics, &$date_arrays, $order_date ) {
		$bump_product_id      = $order->get_meta( '_wcf_bump_product' );
		$multiple_obs         = $order->get_meta( '_wcf_bump_products' );
		$separate_offer_order = $order->get_meta( '_cartflows_parent_flow_id' );

		// Pre-check if bump product ID exists.
		$has_bump_product = ! empty( $bump_product_id );

		// Process merged orders (default behavior).
		if ( empty( $separate_offer_order ) ) {

			foreach ( $order->get_items() as $item_id => $item_data ) {
				$item_product_id = $item_data->get_product_id();
				$item_total      = $item_data->get_total();
				$is_upsell       = wc_get_order_item_meta( $item_id, '_cartflows_upsell', true );
				$is_downsell     = wc_get_order_item_meta( $item_id, '_cartflows_downsell', true );

				// Old single order bump.
				if ( $has_bump_product && $item_product_id == $bump_product_id ) {
					$metrics['total_bump_revenue_raw']                  += $item_total;
					$date_arrays['bump_revenue_by_date'][ $order_date ] += $item_total;
				}

				// Upsell or Downsell.
				if ( 'yes' === $is_upsell || 'yes' === $is_downsell ) {
					$offer_revenue                                        = (float) $item_total;
					$metrics['total_offers_revenue_raw']                 += $offer_revenue;
					$date_arrays['offer_revenue_by_date'][ $order_date ] += $offer_revenue;
				}
			}

			// Multiple order bumps.
			if ( is_array( $multiple_obs ) && ! empty( $multiple_obs ) ) {
				foreach ( $multiple_obs as $key => $data ) {
					$bump_price                         = number_format( $data['price'], wc_get_price_decimals(), '.', '' );
					$metrics['total_bump_revenue_raw'] += $bump_price;
					$date_arrays['bump_revenue_by_date'][ $order_date ] += $bump_price;
				}
			}
		} else {
			// Process separate offer orders.
			$is_offer    = $order->get_meta( '_cartflows_offer' );
			$order_total = $order->get_total();

			if ( 'yes' === $is_offer ) {
				$offer_revenue                                        = (float) $order_total;
				$metrics['total_offers_revenue_raw']                 += $offer_revenue;
				$date_arrays['offer_revenue_by_date'][ $order_date ] += $offer_revenue;
			}
		}

		return $metrics;
	}

	/**
	 * Calculate derived metrics for funnel analytics.
	 * Calculates AOV and RPV metrics.
	 *
	 * @param array $metrics Metrics array.
	 * @param array $all_dates All dates in range.
	 * @param array $date_arrays Date-based arrays.
	 * @return array Updated metrics.
	 */
	private function calculate_funnel_derived_metrics( $metrics, $all_dates, &$date_arrays ) {

		// Calculate Average Order Value (AOV).
		if ( $metrics['total_orders'] > 0 ) {
			$metrics['avg_order_value_raw'] = $metrics['total_revenue_raw'] / $metrics['total_orders'];
			$metrics['avg_order_value']     = wcf_pro_analytics()->format_price( $metrics['avg_order_value_raw'] );
		}

		// Calculate Revenue Per Visit (RPV).
		if ( $metrics['total_visits'] > 0 ) {
			$metrics['revenue_per_visit_raw'] = $metrics['total_revenue_raw'] / $metrics['total_visits'];
			$metrics['revenue_per_visit']     = wcf_pro_analytics()->format_price( $metrics['revenue_per_visit_raw'] );
		}

		// Calculate AOV and RPV by date.
		foreach ( $all_dates as $date ) {
			// AOV by date.
			if ( isset( $date_arrays['orders_by_date'][ $date ] ) && $date_arrays['orders_by_date'][ $date ] > 0 ) {
				$date_arrays['avg_order_value_by_date'][ $date ] = $date_arrays['revenue_by_date'][ $date ] / $date_arrays['orders_by_date'][ $date ];
			}

			// RPV by date.
			if ( isset( $date_arrays['visits_by_date'][ $date ] ) && $date_arrays['visits_by_date'][ $date ] > 0 ) {
				$date_arrays['revenue_per_visit_by_date'][ $date ] = $date_arrays['revenue_by_date'][ $date ] / $date_arrays['visits_by_date'][ $date ];
			}
		}

		return $metrics;
	}

	/**
	 * Get top performing funnels based on revenue.
	 *
	 * @param array $flows_analytics Array of flow analytics data.
	 * @param int   $limit Number of top funnels to return (default: 4).
	 * @return array Top performing funnels sorted by revenue.
	 */
	public function get_top_performing_funnels( $flows_analytics, $limit = 4 ) {

		if ( empty( $flows_analytics ) || ! is_array( $flows_analytics ) ) {
			return array();
		}

		// Return only the top N funnels.
		return array_slice( $flows_analytics, 0, $limit );
	}
}

Cartflows_Pro_Analytics_Funnels::get_instance();

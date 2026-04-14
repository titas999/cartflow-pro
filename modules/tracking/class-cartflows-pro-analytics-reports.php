<?php
/**
 * Flow
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;
/**
 * Analytics reports class.
 */
class Cartflows_Pro_Analytics_Reports {

	/**
	 * Member Variable
	 *
	 * @var object instance
	 */
	private static $instance;

	/**
	 * Flow orders
	 *
	 * @var array flow_orders
	 */
	private static $flow_orders = array();

	/**
	 * Flow gross sell
	 *
	 * @var int flow_gross
	 */
	private static $flow_gross = 0;

	/**
	 * Flow visits
	 *
	 * @var array flow_visits
	 */
	private static $flow_visits = array();

	/**
	 * Steps data
	 *
	 * @var array step_data
	 */
	private static $step_data = array();

	/**
	 * Earnings for flow
	 *
	 * @var array flow_earnings
	 */
	private static $flow_earnings = array();

	/**
	 * Report interval
	 *
	 * @var int report_interval
	 */
	private static $report_interval = 30;

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
	 * Constructor function that initializes required actions and hooks
	 */
	public function __construct() {
		add_filter( 'cartflows_home_page_analytics', array( $this, 'get_home_page_analytics_data' ), 10, 6 );
	}

	/**
	 * Get home page analytics.
	 * Orchestrates analytics data retrieval based on screen type.
	 *
	 * @param array  $analytics_data analytics.
	 * @param string $start_date start date.
	 * @param string $end_date end date.
	 * @param string $dashboard_flow_id flow ID (empty for all flows).
	 * @param string $screen_type screen type ('funnels', 'conversions', or 'optins').
	 * @param string $comparison_range_type comparison range type.
	 * @return array Complete analytics data for the requested screen.
	 */
	public function get_home_page_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id, $screen_type, $comparison_range_type = '' ) {
		// Orchestrate analytics based on screen type.
		switch ( $screen_type ) {
			case 'funnels':
				return wcf_pro_analytics()->funnels->get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id, $comparison_range_type );

			case 'conversions':
				return wcf_pro_analytics()->conversions->get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id, $comparison_range_type );

			case 'optins':
				return wcf_pro_analytics()->optin->get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id, $comparison_range_type );

			default:
				// Fallback to funnels if screen type is not recognized.
				return wcf_pro_analytics()->funnels->get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id, $comparison_range_type );
		}
	}

	/**
	 * Get earnings for a flow (delegates to database helper).
	 * Kept for backward compatibility with flow-analytics.php.
	 *
	 * @param string $flow_id Flow ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Earnings data.
	 */
	public function get_earnings( $flow_id, $start_date, $end_date ) {
		$earning_data = wcf_pro_analytics()->database->get_earnings( $flow_id, $start_date, $end_date );

		// Add revenue_per_unique_visitors if not already present.
		if ( ! isset( $earning_data['revenue_per_unique_visits'] ) ) {
			$rpuv_data                                 = wcf_pro_analytics()->calculations->calculate_revenue_per_unique_visitor( $start_date, $end_date, $flow_id );
			$earning_data['revenue_per_unique_visits'] = wcf_pro_analytics()->format_price( $rpuv_data['revenue_per_unique_visitor'] );
		}

		return $earning_data;
	}

	/**
	 * Fetch visits for a flow (delegates to database helper).
	 * Kept for backward compatibility with flow-analytics.php.
	 *
	 * @param int    $flow_id Flow ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $screen_type Whether from flow analytics.
	 * @return array Visits data.
	 */
	public function fetch_visits( $flow_id, $start_date, $end_date, $screen_type = '' ) {
		return wcf_pro_analytics()->database->fetch_visits( $flow_id, $start_date, $end_date, $screen_type );
	}

	/**
	 * Build step analytics data structure (delegates to calculations helper).
	 * Maps visit data and earnings to flow steps.
	 *
	 * @param int   $flow_id Flow ID.
	 * @param array $visits Visits data.
	 * @param array $earning Earning data.
	 * @return array Steps array with analytics data attached.
	 */
	public function build_steps_with_analytics( $flow_id, $visits, $earning ) {
		return wcf_pro_analytics()->calculations->build_steps_with_analytics( $flow_id, $visits, $earning );
	}

	/**
	 * Get revenue of flow.
	 *
	 * @param int $flow_id flow id.
	 * @return int|float
	 */
	public function get_gross_sale_by_flow( $flow_id ) {

		global $wpdb;

		$flow_id     = absint( $flow_id );
		$cache_key   = 'cartflows_gross_sale_' . $flow_id;
		$cache_group = 'cartflows_analytics';

		// Try cache first.
		$cached = wp_cache_get( $cache_key, $cache_group );

		if ( false !== $cached && is_numeric( $cached ) ) {
			return (float) $cached;
		}

		$flow_id = (string) $flow_id;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {

			$orders_table = $wpdb->prefix . 'wc_order_stats';
			$meta_table   = $wpdb->prefix . 'wc_orders_meta';

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"
				SELECT COALESCE(SUM(o.total_sales), 0)
				FROM {$orders_table} o
				INNER JOIN {$meta_table} m
					ON o.order_id = m.order_id
				WHERE o.status IN ('wc-completed', 'wc-processing', 'wc-wcf-main-order')
				AND m.meta_key IN ('_wcf_flow_id', '_cartflows_parent_flow_id')
				AND m.meta_value = %s
				",
				$flow_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		} else {

			$posts_table = $wpdb->posts;
			$meta_table  = $wpdb->postmeta;

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"
				SELECT COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(20,6))), 0)
				FROM {$posts_table} p
				INNER JOIN {$meta_table} pm_total
					ON pm_total.post_id = p.ID
					AND pm_total.meta_key = '_order_total'
				INNER JOIN {$meta_table} pm_flow
					ON pm_flow.post_id = p.ID
				WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-wcf-main-order')
				AND pm_flow.meta_key IN ('_wcf_flow_id', '_cartflows_parent_flow_id')
				AND pm_flow.meta_value = %s
				",
				$flow_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = (float) $wpdb->get_var( $query );

		// Cache for 60 seconds.
		wp_cache_set( $cache_key, $result, $cache_group, 60 );

		return (float) $result;
	}
}

Cartflows_Pro_Analytics_Reports::get_instance();

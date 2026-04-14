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
 * Analytics DB class.
 * Handles database table creation and all analytics database operations.
 */
class Cartflows_Pro_Analytics_Db {

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
	 *  Constructor
	 */
	public function __construct() {
		$this->create_db_tables();
	}

	/**
	 *  Create tables for analytics.
	 */
	public function create_db_tables() {

		global $wpdb;

		if ( get_option( 'cartflows_database_tables_created' ) === 'yes' ) {
			return;
		}

		$visits_db       = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visits_meta_db  = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		// visits db sql command.
		$sql = "CREATE TABLE $visits_db (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            step_id bigint(20) NOT NULL,
            date_visited datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            visit_type enum('new','return'),
            PRIMARY KEY (id)
        ) $charset_collate;\n";

		// visits meta db sql command.
		$sql .= "CREATE TABLE $visits_meta_db (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visit_id bigint(20) NOT NULL,
            meta_key varchar(255) NULL,
            meta_value longtext NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'cartflows_database_tables_created', 'yes' );
	}

	// ============================================
	// Database Query Methods
	// ============================================

	/**
	 * Check if custom order table enabled (HPOS).
	 *
	 * @return bool
	 */
	public function is_custom_order_table_enabled() {
		return class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ? true : false;
	}

	/**
	 * Get order table configuration based on HPOS settings.
	 *
	 * @return array Order table configuration.
	 */
	public function get_order_table_config() {
		global $wpdb;

		if ( $this->is_custom_order_table_enabled() ) {
			return array(
				'order_table'         => $wpdb->prefix . 'wc_orders',
				'order_meta_table'    => $wpdb->prefix . 'wc_orders_meta',
				'order_id_key'        => 'order_id',
				'order_date_key'      => 'date_created_gmt',
				'order_status_key'    => 'status',
				'billing_email_field' => 'tb1.billing_email',
				'order_total_field'   => 'tb1.total_amount',
				'order_id_field'      => 'tb1.id',
			);
		}

		return array(
			'order_table'         => $wpdb->prefix . 'posts',
			'order_meta_table'    => $wpdb->prefix . 'postmeta',
			'order_id_key'        => 'post_id',
			'order_date_key'      => 'post_date',
			'order_status_key'    => 'post_status',
			'billing_email_field' => 'tb_email.meta_value',
			'order_total_field'   => 'tb_total.meta_value',
			'order_id_field'      => 'tb1.ID',
		);
	}

	/**
	 * Prepare where items for query.
	 *
	 * @param array $conditions conditions to prepare WHERE query.
	 * @return string
	 */
	public function get_items_query_where( $conditions ) {
		global $wpdb;

		$where_conditions = array();
		$where_values     = array();

		foreach ( $conditions as $key => $condition ) {
			if ( false !== stripos( $key, 'IN' ) ) {
				$where_conditions[] = $key . '( %s )';
			} else {
				$where_conditions[] = $key . '= %s';
			}

			$where_values[] = $condition;
		}

		if ( ! empty( $where_conditions ) ) {
			// @codingStandardsIgnoreStart
			return $wpdb->prepare( 'WHERE 1 = 1 AND ' . implode( ' AND ', $where_conditions ), $where_values );
			// @codingStandardsIgnoreEnd
		} else {
			return '';
		}
	}

	/**
	 * Get orders by flows for date range.
	 *
	 * @param string $start_date start date.
	 * @param string $end_date end date.
	 * @param string $flow_id flow ID (optional).
	 * @return array|object|null
	 */
	public function get_orders_by_flows( $start_date, $end_date, $flow_id = '' ) {
		global $wpdb;

		if ( $this->is_custom_order_table_enabled() ) {
			// HPOS usage is enabled.
			$conditions = array(
				'tb1.type' => 'shop_order',
			);

			$order_date_key   = 'date_created_gmt';
			$order_status_key = 'status';
			$order_id_key     = 'order_id';
			$order_table      = $wpdb->prefix . 'wc_orders';
			$order_meta_table = $wpdb->prefix . 'wc_orders_meta';
		} else {
			// Traditional CPT-based orders are in use.
			$conditions       = array(
				'tb1.post_type' => 'shop_order',
			);
			$order_date_key   = 'post_date';
			$order_status_key = 'post_status';
			$order_id_key     = 'post_id';
			$order_table      = $wpdb->prefix . 'posts';
			$order_meta_table = $wpdb->prefix . 'postmeta';
		}

		$where = $this->get_items_query_where( $conditions );

		// Build dynamic WHERE clause with proper escaping.
		$where_params = array();

		if ( ! empty( $flow_id ) ) {
			$where         .= ' AND tb2.meta_value = %s';
			$where_params[] = $flow_id;
		}

		$where         .= ' AND ( tb1.' . $order_date_key . ' BETWEEN %s AND %s )';
		$where_params[] = $start_date;
		$where_params[] = $end_date;

		$where .= " AND ( ( tb2.meta_key = '_wcf_flow_id' ) OR ( tb2.meta_key = '_cartflows_parent_flow_id' ) )";
		$where .= ' AND tb1.' . $order_status_key . " IN ( 'wc-completed', 'wc-processing', 'wc-wcf-main-order' )";

		// Prepare WHERE clause with parameters.
		$where = $wpdb->prepare( $where, $where_params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$query = 'SELECT tb1.ID, DATE( tb1.' . $order_date_key . ' ) date, tb2.meta_value FROM ' . $order_table . ' tb1
		INNER JOIN ' . $order_meta_table . ' tb2
		ON tb1.ID = tb2.' . $order_id_key . '
		' . $where;

		$orders = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $orders;
	}

	/**
	 * Get orders data for flow.
	 *
	 * @param int|string $flow_id flow id.
	 * @param string     $start_date start date.
	 * @param string     $end_date end date.
	 * @return array
	 */
	public function get_orders_by_flow( $flow_id, $start_date, $end_date ) {
		global $wpdb;

		$start_date = $start_date ? $start_date : gmdate( 'Y-m-d' );
		$end_date   = $end_date ? $end_date : gmdate( 'Y-m-d' );

		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );

		if ( $analytics_reset_date > $start_date ) {
			$start_date = $analytics_reset_date;
		}

		if ( $this->is_custom_order_table_enabled() ) {
			// HPOS usage is enabled.
			$conditions       = array(
				'tb1.type' => 'shop_order',
			);
			$order_date_key   = 'date_created_gmt';
			$order_status_key = 'status';
			$order_id_key     = 'order_id';
			$order_table      = $wpdb->prefix . 'wc_orders';
			$order_meta_table = $wpdb->prefix . 'wc_orders_meta';
		} else {
			// Traditional CPT-based orders are in use.
			$conditions       = array(
				'tb1.post_type' => 'shop_order',
			);
			$order_date_key   = 'post_date';
			$order_status_key = 'post_status';
			$order_id_key     = 'post_id';
			$order_table      = $wpdb->prefix . 'posts';
			$order_meta_table = $wpdb->prefix . 'postmeta';
		}

		$where = $this->get_items_query_where( $conditions );

		// Build dynamic WHERE clause with proper escaping.
		$where_params = array();

		if ( ! empty( $flow_id ) ) {
			$where         .= ' AND tb2.meta_value = %s';
			$where_params[] = $flow_id;
		}

		$where         .= ' AND ( tb1.' . $order_date_key . ' BETWEEN %s AND %s )';
		$where_params[] = $start_date;
		$where_params[] = $end_date;

		$where .= " AND ( ( tb2.meta_key = '_wcf_flow_id' ) OR ( tb2.meta_key = '_cartflows_parent_flow_id' ) )";
		$where .= ' AND tb1.' . $order_status_key . " IN ( 'wc-completed', 'wc-processing', 'wc-wcf-main-order' )";
		$where .= ' AND tb3.meta_key IS NULL';

		// Prepare WHERE clause with parameters.
		$where = $wpdb->prepare( $where, $where_params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$query = 'SELECT tb1.ID, DATE( tb1.' . $order_date_key . ' ) date FROM ' . $order_table . ' tb1
		INNER JOIN ' . $order_meta_table . ' tb2
		ON tb1.ID = tb2.' . $order_id_key . '
		LEFT JOIN ' . $order_meta_table . ' tb3
		ON tb1.ID = tb3.' . $order_id_key . " AND tb3.meta_key = '_subscription_renewal'
		" . $where;

		$orders = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $orders;
	}

	/**
	 * Fetch total visits.
	 *
	 * @param array  $flow_ids flows id.
	 * @param string $start_date start date.
	 * @param string $end_date end date.
	 * @return array|object|null
	 */
	public function fetch_visits_of_all_flows( $flow_ids, $start_date, $end_date ) {
		global $wpdb;

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		//phpcs:disable WordPress.DB.PreparedSQL
		$query = $wpdb->prepare(
			"SELECT DATE_FORMAT( date_visited, '%%Y-%%m-%%d') AS OrderDate,
			COUNT( DISTINCT( $visit_db.id ) ) AS total_visits
			FROM $visit_db INNER JOIN $visit_meta_db ON $visit_db.id = $visit_meta_db.visit_id
			WHERE 1 = 1
			AND date_visited >= %s
			AND date_visited <= %s
			GROUP BY OrderDate
			ORDER BY OrderDate ASC",
			$start_date,
			$end_date
		);

		// Query is prepared above.
		$visits = $wpdb->get_results( $query );//phpcs:ignore WordPress.DB.DirectDatabaseQuery

		//phpcs:enable WordPress.DB.PreparedSQL

		return $visits;
	}

	/**
	 * Fetch visits for a flow.
	 *
	 * @param int|string $flow_id flow id.
	 * @param string     $start_date start date.
	 * @param string     $end_date end date.
	 * @param string     $screen_type screen type ('funnels', 'conversions', or 'optins').
	 * @return array
	 */
	public function fetch_visits( $flow_id, $start_date, $end_date, $screen_type = '' ) {
		// Normalize dates.
		$start_date = $start_date ? $start_date : gmdate( 'Y-m-d' );
		$end_date   = $end_date ? $end_date : gmdate( 'Y-m-d' );

		global $wpdb;

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Need to look into date format later.
		$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );

		if ( $analytics_reset_date > $start_date ) {
			$start_date = $analytics_reset_date;
		}

		$steps     = wcf()->flow->get_steps( $flow_id );
		$all_steps = array();

		foreach ( $steps as $s_key => $s_data ) {
			if ( isset( $s_data['ab-test'] ) ) {
				if ( isset( $s_data['ab-test-variations'] ) && ! empty( $s_data['ab-test-variations'] ) ) {
					foreach ( $s_data['ab-test-variations'] as $v_key => $v_data ) {
						$all_steps[] = $v_data['id'];
					}
				} else {
					$all_steps[] = $s_data['id'];
				}

				if ( isset( $s_data['ab-test-archived-variations'] ) && ! empty( $s_data['ab-test-archived-variations'] ) ) {
					foreach ( $s_data['ab-test-archived-variations'] as $av_key => $av_data ) {
						$all_steps[] = $av_data['id'];
					}
				}
			} else {
				$all_steps[] = $s_data['id'];
			}
		}

		// Sanitize step IDs as integers to ensure SQL safety.
		$all_steps_sanitized = array_map( 'absint', $all_steps );
		$step_ids            = implode( ', ', $all_steps_sanitized );

		if ( empty( $step_ids ) ) {
			return array(
				'step_id'       => 0,
				'OrderDate'     => 0,
				'total_visits'  => 0,
				'unique_visits' => 0,
				'conversions'   => 0,
				'revenue'       => 0,
			);
		}

		$group_by = 'step_id';
		if ( 'funnels' === $screen_type || 'conversions' === $screen_type ) {
			$group_by = 'OrderDate';
		}

		// Build query with sanitized step IDs.
		// Step IDs are sanitized via absint() above, making the IN clause safe.
		// Table names cannot be parameterized - they must be interpolated.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT step_id,
			 DATE_FORMAT( date_visited, '%%Y-%%m-%%d') AS OrderDate,
			 COUNT( DISTINCT( $visit_db.id ) ) AS total_visits,
			 COUNT( DISTINCT( CASE WHEN visit_type = 'new'
			 THEN $visit_db.id ELSE NULL END ) ) AS unique_visits,
			 COUNT( CASE WHEN $visit_meta_db.meta_key = 'conversion'
			 AND $visit_meta_db.meta_value = 'yes'
			 THEN step_id ELSE NULL END ) AS conversions
			 FROM $visit_db INNER JOIN $visit_meta_db ON $visit_db.id = $visit_meta_db.visit_id
			 WHERE step_id IN ( $step_ids )
			 AND ( date_visited BETWEEN %s AND %s )
			 GROUP BY $group_by
			 ORDER BY NULL",
			$start_date,
			$end_date
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$visits = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// phpcs:enable WordPress.DB.PreparedSQL
		$visited_steps     = wp_list_pluck( (array) $visits, 'step_id' );
		$non_visited_steps = array_diff( $all_steps, $visited_steps );

		// Non visited steps.
		if ( $non_visited_steps ) {
			$non_visit = array(
				'step_id'       => 0,
				'OrderDate'     => 0,
				'total_visits'  => 0,
				'unique_visits' => 0,
				'conversions'   => 0,
				'revenue'       => 0,
			);

			foreach ( $non_visited_steps as $non_visited_step ) {
				$non_visit['step_id'] = $non_visited_step;
				array_push( $visits, (object) $non_visit );
			}
		}

		$step_ids_array = wp_list_pluck( (array) $steps, 'id' );
		usort(
			$visits,
			function ( $a, $b ) use ( $all_steps ) {
				return array_search( intval( $a->step_id ), $all_steps, true ) - array_search( intval( $b->step_id ), $all_steps, true );
			}
		);

		// phpcs:enable
		return $visits;
	}

	/**
	 * Calculate earning.
	 *
	 * @param string $flow_id flow_id.
	 * @param string $start_date start date.
	 * @param string $end_date end date.
	 * @return array
	 */
	public function get_earnings( $flow_id, $start_date, $end_date ) {
		$orders                   = $this->get_orders_by_flow( $flow_id, $start_date, $end_date );
		$gross_sale               = 0;
		$checkout_total           = 0;
		$avg_order_value          = 0;
		$total_bump_offer_earning = 0;
		$checkout_earnings        = array();
		$offer_earnings           = array();
		$order_count              = 0;
		$upsell_revenue           = 0;

		if ( ! empty( $orders ) ) {
			// Bulk fetch all WC_Order objects at once instead of calling wc_get_order() in loop.
			$orders = wp_list_pluck( $orders, 'ID' );

			foreach ( $orders as $order_id ) {
				
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				
				$user_id = $order->get_user_id();

				$order_total = $order->get_total();
				if ( ! $order->has_status( 'cancelled' ) ) {
					$gross_sale    += (float) $order_total;
					$checkout_total = (float) $order_total;
				}
				$bump_product_id      = $order->get_meta( '_wcf_bump_product' );
				$multiple_obs         = $order->get_meta( '_wcf_bump_products' );
				$separate_offer_order = $order->get_meta( '_cartflows_parent_flow_id' );
				$checkout_id          = $order->get_meta( '_wcf_checkout_id' );
				$optin_id             = $order->get_meta( '_wcf_optin_id' );

				if ( empty( $separate_offer_order ) ) {
					// We are doing this for main order and not for the other order such as Upsell/Downsells.
					++$order_count;

					foreach ( $order->get_items() as $item_id => $item_data ) {
						$item_product_id = $item_data->get_product_id();
						$item_total      = $item_data->get_total();
						$is_upsell       = wc_get_order_item_meta( $item_id, '_cartflows_upsell', true );
						$is_downsell     = wc_get_order_item_meta( $item_id, '_cartflows_downsell', true );
						$offer_step_id   = wc_get_order_item_meta( $item_id, '_cartflows_step_id', true );
						$optin_id        = wc_get_order_item_meta( $item_id, '_wcf_optin_id', true );

						if ( 'yes' === $is_upsell ) {
							$checkout_total -= $item_total;

							if ( ! isset( $offer_earnings[ $offer_step_id ] ) ) {
								$offer_earnings[ $offer_step_id ] = 0;
							}
							$offer_earnings[ $offer_step_id ] += number_format( (float) $item_total, 2, '.', '' );
							$upsell_revenue                   += number_format( (float) $item_total, 2, '.', '' );
						}

						if ( 'yes' === $is_downsell ) {
							$checkout_total -= $item_total;

							if ( ! isset( $offer_earnings[ $offer_step_id ] ) ) {
								$offer_earnings[ $offer_step_id ] = 0;
							}

							$offer_earnings[ $offer_step_id ] += number_format( (float) $item_total, 2, '.', '' );
						}

						if ( $item_product_id == $bump_product_id ) {
							$total_bump_offer_earning += $item_total;
							$checkout_total           -= $item_total;
						}
					}
					// Multiple order bump.
					if ( is_array( $multiple_obs ) && ! empty( $multiple_obs ) ) {
						foreach ( $multiple_obs as $key => $data ) {
							$total_bump_offer_earning += number_format( $data['price'], wc_get_price_decimals(), '.', '' );
						}
					}
				} else {
					foreach ( $order->get_items() as $item_id => $item_data ) {
						// Calculate the current upsell/downsell's earnings for the same order.
						$is_offer      = $order->get_meta( '_cartflows_offer' );
						$offer_step_id = $order->get_meta( '_cartflows_offer_step_id', true );
						$optin_id      = wc_get_order_item_meta( $item_id, '_wcf_optin_id', true );
						++$order_count;

						if ( 'yes' === $is_offer ) {
							$checkout_total -= $order_total;

							if ( ! isset( $offer_earnings[ $offer_step_id ] ) ) {
								$offer_earnings[ $offer_step_id ] = 0;
							}

							$offer_earnings[ $offer_step_id ] += number_format( (float) $order_total, 2, '.', '' );

							$step_type = function_exists( 'wcf' ) ? wcf()->utils->get_step_type( $offer_step_id ) : '';
							if ( 'upsell' === $step_type || 'downsell' === $step_type ) {
								$upsell_revenue += number_format( (float) $order_total, 2, '.', '' );
							}
						}
					}
				}

				if ( ! empty( $checkout_id ) ) {
					if ( ! isset( $checkout_earnings[ $checkout_id ] ) ) {
						$checkout_earnings[ $checkout_id ] = 0;
					}

					$checkout_earnings[ $checkout_id ] = $checkout_earnings[ $checkout_id ] + $checkout_total;
				}
			}

			if ( 0 !== $order_count ) {
				$avg_order_value = $gross_sale / $order_count;
			}
		}

		$all_earning_data = array(
			'order_count'     => $order_count,
			'avg_order_value' => wcf_pro_analytics()->format_price( $avg_order_value ),
			'gross_sale'      => wcf_pro_analytics()->format_price( $gross_sale ),
			'checkout_sale'   => wcf_pro_analytics()->format_price( $checkout_total ),
			'offer'           => $offer_earnings,
			'checkout'        => $checkout_earnings,
			'bump_offer'      => wcf_pro_analytics()->format_price( $total_bump_offer_earning ),
		);

		// Calculate total visits for the flow and date range.
		$visits       = $this->fetch_visits( $flow_id, $start_date, $end_date );
		$total_visits = 0;
		if ( is_array( $visits ) ) {
			foreach ( $visits as $visit ) {
				if ( isset( $visit->total_visits ) ) {
					$total_visits += (int) $visit->total_visits;
				}
			}
		}

		// Calculate revenue per visit.
		$revenue_per_visit = $total_visits > 0 ? $gross_sale / $total_visits : 0;

		$all_earning_data['revenue_per_visit'] = wcf_pro_analytics()->format_price( $revenue_per_visit );
		$all_earning_data['upsell_revenue']    = wcf_pro_analytics()->format_price( $upsell_revenue );

		// Note: calculate_revenue_per_unique_visitor will be called from calculations helper.
		// This will be handled in the main class orchestration.

		return $all_earning_data;
	}
}

Cartflows_Pro_Analytics_Db::get_instance();

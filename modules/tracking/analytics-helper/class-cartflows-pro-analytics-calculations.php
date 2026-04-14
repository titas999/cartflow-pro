<?php
/**
 * Analytics Calculations
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Analytics calculations class.
 * Handles all calculations, metrics processing, optin analytics, and product analytics.
 */
class Cartflows_Pro_Analytics_Calculations {

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

	// ============================================
	// Core Calculation Methods
	// ============================================

	/**
	 * Calculate analytics for all flows in the given date range.
	 *
	 * @param string $start_date start date.
	 * @param string $end_date end date.
	 * @return array Array containing analytics data grouped by flow ID.
	 */
	public function calculate_all_flows_analytics( $start_date, $end_date ) {
		// Get all published flows.
		$args = array(
			'post_type'      => CARTFLOWS_FLOW_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$flow_ids = get_posts( $args );

		if ( empty( $flow_ids ) ) {
			return array();
		}

		// Build step-to-flow mappings (including AB test variations and archived variations).
		$mappings              = $this->build_flow_step_mappings( $flow_ids );
		$step_to_flow_map      = $mappings['step_to_flow_map'];
		$flow_all_steps        = $mappings['flow_all_steps'];
		$flow_conversion_steps = $mappings['flow_conversion_steps'];

		// Prepare all step IDs for visit query.
		$all_step_ids = array();
		foreach ( $flow_ids as $flow_id ) {
			if ( ! empty( $flow_all_steps[ $flow_id ] ) ) {
				$all_step_ids = array_merge( $all_step_ids, $flow_all_steps[ $flow_id ] );
			}
		}

		$all_step_ids = array_unique( $all_step_ids );

		if ( empty( $all_step_ids ) ) {
			return array();
		}

		// Format dates for SQL query.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Fetch visit data for all steps across all flows.
		$all_visit_results = $this->fetch_all_flows_visit_data( $all_step_ids, $start_date, $end_date );

		// Aggregate visits by flow.
		$flows_analytics = $this->aggregate_visits_by_flow( $all_visit_results, $step_to_flow_map, $flow_conversion_steps, $flow_ids );

		// Fetch all orders ONCE for all flows instead of per-flow queries.
		// Determine earliest start date across all flows (considering analytics reset dates).
		$earliest_start_date = $start_date;
		foreach ( $flow_ids as $flow_id ) {
			$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );
			if ( ! empty( $analytics_reset_date ) && $analytics_reset_date < $earliest_start_date ) {
				$earliest_start_date = $analytics_reset_date;
			}
		}

		// Fetch ALL orders for ALL flows in a single query (empty flow_id = all flows).
		$all_orders = wcf_pro_analytics()->database->get_orders_by_flows( $earliest_start_date, $end_date, '' );

		// Group orders by flow_id for efficient in-memory processing.
		$orders_grouped_by_flow_id = array();
		if ( ! empty( $all_orders ) && is_array( $all_orders ) ) {
			foreach ( $all_orders as $order_row ) {
				$order_flow_id = $order_row->meta_value;
				if ( ! isset( $orders_grouped_by_flow_id[ $order_flow_id ] ) ) {
					$orders_grouped_by_flow_id[ $order_flow_id ] = array();
				}
				$orders_grouped_by_flow_id[ $order_flow_id ][] = $order_row;
			}
		}

		// Calculate revenue and conversion rates for each flow.
		$flows_analytics = $this->calculate_flow_revenues_and_conversion_rates( $flow_ids, $flows_analytics, $orders_grouped_by_flow_id, $all_visit_results, $flow_conversion_steps, $start_date, $end_date );

		// Convert to array for sorting (if it's not already).
		$flows_array = array_values( $flows_analytics );

		// Sort by revenue in descending order (highest first).
		usort(
			$flows_array,
			function ( $a, $b ) {
				$revenue_a = isset( $a['revenue'] ) ? wcf_pro_analytics()->extract_price( $a['revenue'] ) : 0;
				$revenue_b = isset( $b['revenue'] ) ? wcf_pro_analytics()->extract_price( $b['revenue'] ) : 0;

				// Descending order.
				if ( $revenue_a === $revenue_b ) {
					return 0;
				}
				return ( $revenue_a > $revenue_b ) ? -1 : 1;
			}
		);

		return $flows_array;
	}

	/**
	 * Build step-to-flow mappings for all flows.
	 * Maps each step (including AB test variations and archived variations) to its parent flow.
	 *
	 * @param array $flow_ids Array of flow IDs.
	 * @return array Contains step_to_flow_map, flow_all_steps, and flow_conversion_steps arrays.
	 */
	private function build_flow_step_mappings( $flow_ids ) {
		$step_to_flow_map      = array();
		$flow_all_steps        = array(); // All steps including landing/thankyou.
		$flow_conversion_steps = array(); // Only steps that can have conversions (exclude landing/thankyou).

		foreach ( $flow_ids as $flow_id ) {
			$flow_all_steps[ $flow_id ]        = array();
			$flow_conversion_steps[ $flow_id ] = array();

			// Get all steps for this flow.
			$steps = wcf()->flow->get_steps( $flow_id );
			foreach ( $steps as $s_key => $s_data ) {
				$step_id                      = $s_data['id'];
				$flow_all_steps[ $flow_id ][] = $step_id;
				$step_to_flow_map[ $step_id ] = $flow_id;

				// Get the main step type.
				$main_step_type = wcf()->utils->get_step_type( $step_id );

				// Check if this step can have conversions (exclude thankyou and landing).
				$can_convert = ( 'thankyou' !== $main_step_type );

				// Handle AB test variations.
				if ( isset( $s_data['ab-test'] ) ) {
					if ( isset( $s_data['ab-test-variations'] ) && ! empty( $s_data['ab-test-variations'] ) ) {
						foreach ( $s_data['ab-test-variations'] as $v_key => $v_data ) {
							$variation_id                      = $v_data['id'];
							$flow_all_steps[ $flow_id ][]      = $variation_id;
							$step_to_flow_map[ $variation_id ] = $flow_id;

							// Check variation step type as well.
							$variation_step_type = wcf()->utils->get_step_type( $variation_id );
							if ( 'thankyou' !== $variation_step_type ) {
								$flow_conversion_steps[ $flow_id ][] = $variation_id;
							}
						}
					} elseif ( $can_convert ) {
						// AB test enabled but no variations yet, use main step if it can convert.
						$flow_conversion_steps[ $flow_id ][] = $step_id;
					}

					// Handle archived variations.
					if ( isset( $s_data['ab-test-archived-variations'] ) && ! empty( $s_data['ab-test-archived-variations'] ) ) {
						foreach ( $s_data['ab-test-archived-variations'] as $av_key => $av_data ) {
							$archived_id                      = $av_data['id'];
							$flow_all_steps[ $flow_id ][]     = $archived_id;
							$step_to_flow_map[ $archived_id ] = $flow_id;

							// Check archived variation step type as well.
							$archived_step_type = wcf()->utils->get_step_type( $archived_id );
							if ( 'thankyou' !== $archived_step_type ) {
								$flow_conversion_steps[ $flow_id ][] = $archived_id;
							}
						}
					}
				} elseif ( $can_convert ) {
					// Regular step (no AB test).
					$flow_conversion_steps[ $flow_id ][] = $step_id;
				}
			}
		}

		return array(
			'step_to_flow_map'      => $step_to_flow_map,
			'flow_all_steps'        => $flow_all_steps,
			'flow_conversion_steps' => $flow_conversion_steps,
		);
	}

	/**
	 * Fetch visit data for all steps across all flows.
	 *
	 * @param array  $all_step_ids Array of all step IDs.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Visit results indexed by step_id.
	 */
	private function fetch_all_flows_visit_data( $all_step_ids, $start_date, $end_date ) {
		global $wpdb;

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		// Sanitize step IDs as integers to ensure SQL safety.
		$all_step_ids_sanitized = array_map( 'absint', $all_step_ids );
		$step_ids_str           = implode( ', ', $all_step_ids_sanitized );

		// Single query to get all visit data grouped by step_id.
		// Step IDs are sanitized via absint() above, making the IN clause safe.
		// Table names cannot be parameterized - they must be interpolated.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fetch_analytics_query = $wpdb->prepare(
			"SELECT
			$visit_db.step_id,
			COUNT( DISTINCT( $visit_db.id ) ) AS total_visits,
			COUNT( DISTINCT( CASE WHEN $visit_db.visit_type = 'new'
			THEN $visit_db.id ELSE NULL END ) ) AS unique_visits,
			COUNT( CASE WHEN $visit_meta_db.meta_key = 'conversion'
			AND $visit_meta_db.meta_value = 'yes'
			THEN $visit_db.id ELSE NULL END ) AS conversions
			FROM $visit_db
			INNER JOIN $visit_meta_db ON $visit_db.id = $visit_meta_db.visit_id
			WHERE $visit_db.step_id IN ( $step_ids_str )
			AND ( $visit_db.date_visited BETWEEN %s AND %s )
			GROUP BY $visit_db.step_id",
			$start_date,
			$end_date
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( $fetch_analytics_query, OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Aggregate visit data by flow from step-level data.
	 *
	 * @param array $all_visit_results Visit results indexed by step_id.
	 * @param array $step_to_flow_map Map of step_id => flow_id.
	 * @param array $flow_conversion_steps Array of conversion steps per flow.
	 * @param array $flow_ids Array of flow IDs.
	 * @return array Flows analytics with visits/conversions aggregated.
	 */
	private function aggregate_visits_by_flow( $all_visit_results, $step_to_flow_map, $flow_conversion_steps, $flow_ids ) {
		$flows_analytics = array();

		// Initialize analytics for all flows.
		foreach ( $flow_ids as $flow_id ) {
			$flows_analytics[ $flow_id ] = array(
				'flow_id'         => $flow_id,
				'flow_title'      => get_the_title( $flow_id ),
				'total_visits'    => 0,
				'unique_visits'   => 0,
				'conversions'     => 0,
				'conversion_rate' => 0,
				'revenue'         => wcf_pro_analytics()->format_price( 0 ),
			);
		}

		// Build O(1) lookup map for conversion steps.
		$conversion_steps_lookup = array();
		foreach ( $flow_conversion_steps as $flow_id => $steps ) {
			foreach ( $steps as $step_id ) {
				$conversion_steps_lookup[ $step_id ] = true;
			}
		}

		// Aggregate visit data by flow.
		foreach ( $all_visit_results as $step_id => $visit_data ) {
			if ( isset( $step_to_flow_map[ $step_id ] ) ) {
				$flow_id = $step_to_flow_map[ $step_id ];

				// Add to total visits and unique visits (all steps).
				$flows_analytics[ $flow_id ]['total_visits']  += (int) $visit_data->total_visits;
				$flows_analytics[ $flow_id ]['unique_visits'] += (int) $visit_data->unique_visits;

				// Only add conversions if this step is in the conversion steps list.
				if ( isset( $conversion_steps_lookup[ $step_id ] ) ) {
					$flows_analytics[ $flow_id ]['conversions'] += (int) $visit_data->conversions;
				}
			}
		}

		return $flows_analytics;
	}

	/**
	 * Calculate revenue and conversion rates for each flow.
	 *
	 * @param array  $flow_ids Array of flow IDs.
	 * @param array  $flows_analytics Flows analytics array to update.
	 * @param array  $orders_grouped_by_flow_id Orders grouped by flow_id.
	 * @param array  $all_visit_results Visit results indexed by step_id.
	 * @param array  $flow_conversion_steps Array of conversion steps per flow.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Updated flows analytics with revenue and conversion rates.
	 */
	private function calculate_flow_revenues_and_conversion_rates( $flow_ids, $flows_analytics, $orders_grouped_by_flow_id, $all_visit_results, $flow_conversion_steps, $start_date, $end_date ) {
		// Bulk fetch all WC_Order objects at once for all flows.
		if ( empty( $orders_grouped_by_flow_id ) || empty( $flow_ids ) ) {
			return $flows_analytics;
		}

		foreach ( $flow_ids as $flow_id ) {
			// Check analytics reset date.
			$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );
			$flow_start_date      = $start_date;

			if ( $analytics_reset_date > $start_date ) {
				$flow_start_date = $analytics_reset_date;
			}

			// Format dates if needed.
			$flow_start_date = wcf_pro_analytics()->format_date_time( $flow_start_date, false );
			$flow_end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

			// Calculate conversion rate based on conversion steps visits.
			$conversion_steps_visits = 0;
			if ( ! empty( $flow_conversion_steps[ $flow_id ] ) ) {
				foreach ( $flow_conversion_steps[ $flow_id ] as $conv_step_id ) {
					if ( isset( $all_visit_results[ $conv_step_id ] ) ) {
						$conversion_steps_visits += (int) $all_visit_results[ $conv_step_id ]->total_visits;
					}
				}
			}

			$conversions = $flows_analytics[ $flow_id ]['conversions'];

			if ( $conversion_steps_visits > 0 ) {
				$conversion_rate                                = ( $conversions / $conversion_steps_visits ) * 100;
				$flows_analytics[ $flow_id ]['conversion_rate'] = number_format( (float) $conversion_rate, 2, '.', '' );
			}

			// Calculate revenue for this flow from pre-fetched orders.
			$flow_revenue = 0;
			if ( isset( $orders_grouped_by_flow_id[ $flow_id ] ) ) {
				$flow_orders = $orders_grouped_by_flow_id[ $flow_id ];

				// Filter orders by flow-specific date range (considering analytics reset).
				$flow_start_ts = strtotime( $flow_start_date );
				$flow_end_ts   = strtotime( $flow_end_date );

				foreach ( $flow_orders as $order_row ) {
					$order_date_ts = strtotime( $order_row->date );
					// Only include orders within this flow's specific date range.
					if ( $order_date_ts >= $flow_start_ts && $order_date_ts <= $flow_end_ts ) {
							$order = wc_get_order( $order_row->ID );

						if ( $order instanceof WC_Order && ! $order->has_status( 'cancelled' ) ) {
							$flow_revenue += (float) $order->get_total();
						}
					}
				}
			}

			$flows_analytics[ $flow_id ]['revenue'] = wcf_pro_analytics()->format_price( $flow_revenue );
		}

		return $flows_analytics;
	}

	/**
	 * Calculate Revenue Per Unique Visitor.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $flow_id Flow ID (empty for all flows).
	 * @return array Contains total_revenue, total_unique_visitors, and revenue_per_unique_visitor.
	 */
	public function calculate_revenue_per_unique_visitor( $start_date, $end_date, $flow_id = '' ) {
		// Format dates.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Use shared helper method to fetch order data (DRY - eliminates duplication).
		$results = $this->fetch_rpuv_order_data( $start_date, $end_date, $flow_id, false );

		// Process results - differentiate parent vs child orders.
		$email_revenue_map = array();
		$unique_emails     = array();
		$no_email_orders   = array();

		if ( ! empty( $results ) && is_array( $results ) ) {
			foreach ( $results as $row ) {
				$order_id       = intval( $row->order_id );
				$billing_email  = isset( $row->billing_email ) ? trim( $row->billing_email ) : '';
				$order_total    = floatval( $row->order_total );
				$flow_id_meta   = isset( $row->flow_id ) ? trim( $row->flow_id ) : '';
				$parent_flow_id = isset( $row->parent_flow_id ) ? trim( $row->parent_flow_id ) : '';

				// Determine if this is a parent or child order.
				// Parent order: has _wcf_flow_id and NO _cartflows_parent_flow_id.
				// Child order: has _cartflows_parent_flow_id.
				$is_parent_order = ! empty( $flow_id_meta ) && empty( $parent_flow_id );
				$is_child_order  = ! empty( $parent_flow_id );

				if ( empty( $billing_email ) ) {
					// No email.
					if ( $is_parent_order ) {
						// Parent order with no email - track separately as unique.
						$no_email_orders[ $order_id ] = $order_total;
					}
					// Child orders with no email - skip (can't attribute revenue).
				} else {
					// Has email - add revenue to email's total.
					if ( ! isset( $email_revenue_map[ $billing_email ] ) ) {
						$email_revenue_map[ $billing_email ] = 0;
					}
					$email_revenue_map[ $billing_email ] += $order_total;

					// Track as unique email ONLY if it's a parent order.
					if ( $is_parent_order && ! in_array( $billing_email, $unique_emails, true ) ) {
						$unique_emails[] = $billing_email;
					}
				}
			}
		}

		// Calculate totals.
		$total_revenue         = 0;
		$total_unique_visitors = 0;

		// Add revenue from emails and count unique visitors.
		foreach ( $email_revenue_map as $email => $revenue ) {
			$total_revenue += $revenue;

			// Only count as unique visitor if email was seen as parent order.
			if ( in_array( $email, $unique_emails, true ) ) {
				++$total_unique_visitors;
			}
		}

		// Add revenue and count from no-email parent orders.
		foreach ( $no_email_orders as $order_id => $revenue ) {
			$total_revenue += $revenue;
			++$total_unique_visitors;
		}

		// Calculate Revenue Per Unique Visitor.
		$revenue_per_unique_visitor = 0;
		if ( $total_unique_visitors > 0 ) {
			$revenue_per_unique_visitor = $total_revenue / $total_unique_visitors;
		}

		$rpuv_data = array(
			'total_revenue'              => $total_revenue,
			'total_unique_visitors'      => $total_unique_visitors,
			'revenue_per_unique_visitor' => $revenue_per_unique_visitor,
		);

		return $rpuv_data;
	}

	/**
	 * Calculate Revenue Per Unique Visitor by date.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $flow_id Flow ID (empty for all flows).
	 * @param array  $all_dates All dates in the range.
	 * @return array Revenue per unique visitor by date.
	 */
	public function calculate_revenue_per_unique_visitor_by_date( $start_date, $end_date, $flow_id, $all_dates ) {
		// Format dates.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Use shared helper method to fetch order data (DRY - eliminates duplication).
		$results = $this->fetch_rpuv_order_data( $start_date, $end_date, $flow_id, true );

		// Initialize data structures.
		$revenue_by_date         = array();
		$unique_visitors_by_date = array();
		$tracked_emails          = array(); // Track emails we've already counted as unique.
		$tracked_no_email_orders = array(); // Track orders without email (each is unique).
		$cumulative_unique_count = 0;

		// Initialize all dates.
		foreach ( $all_dates as $date ) {
			$revenue_by_date[ $date ]         = 0;
			$unique_visitors_by_date[ $date ] = 0;
		}

		// Process results - differentiate parent vs child orders.
		if ( ! empty( $results ) && is_array( $results ) ) {
			foreach ( $results as $row ) {
				$order_date     = $row->order_date;
				$order_id       = intval( $row->order_id );
				$billing_email  = isset( $row->billing_email ) ? trim( $row->billing_email ) : '';
				$order_total    = floatval( $row->order_total );
				$flow_id_meta   = isset( $row->flow_id ) ? trim( $row->flow_id ) : '';
				$parent_flow_id = isset( $row->parent_flow_id ) ? trim( $row->parent_flow_id ) : '';

				// Determine if this is a parent or child order.
				$is_parent_order = ! empty( $flow_id_meta ) && empty( $parent_flow_id );
				$is_child_order  = ! empty( $parent_flow_id );

				// Add revenue for this date (both parent and child).
				if ( isset( $revenue_by_date[ $order_date ] ) ) {
					$revenue_by_date[ $order_date ] += $order_total;
				}

				// Count unique visitors ONLY for parent orders.
				if ( empty( $billing_email ) ) {
					// No email.
					if ( $is_parent_order ) {
						// Parent order with no email - each is unique.
						if ( ! in_array( $order_id, $tracked_no_email_orders, true ) ) {
							$tracked_no_email_orders[] = $order_id;

							if ( isset( $unique_visitors_by_date[ $order_date ] ) ) {
								++$unique_visitors_by_date[ $order_date ];
							}
							++$cumulative_unique_count;
						}
					}
					// Child orders with no email - skip visitor count.
				} elseif ( $is_parent_order ) {
					// Has email.
					// Parent order - check if first occurrence.
					if ( ! in_array( $billing_email, $tracked_emails, true ) ) {
						$tracked_emails[] = $billing_email;

						if ( isset( $unique_visitors_by_date[ $order_date ] ) ) {
							++$unique_visitors_by_date[ $order_date ];
						}
						++$cumulative_unique_count;
					}
					// Child orders - revenue already added above, don't count as visitor.
				}
			}
		}

		// Calculate RPUV for each date using cumulative unique visitors.
		$rpuv_by_date            = array();
		$running_unique_visitors = 0;

		foreach ( $all_dates as $date ) {
			$running_unique_visitors += $unique_visitors_by_date[ $date ];

			// Calculate RPUV: revenue for this date / cumulative unique visitors up to this date.
			if ( $running_unique_visitors > 0 && $revenue_by_date[ $date ] > 0 ) {
				$rpuv_by_date[ $date ] = $revenue_by_date[ $date ] / $running_unique_visitors;
			} else {
				$rpuv_by_date[ $date ] = 0;
			}
		}

		return $rpuv_by_date;
	}

	/**
	 * Calculate total conversions from visits/visits_meta tables.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $flow_id Flow ID (empty for all flows).
	 * @return int Total conversions count.
	 */
	public function calculate_total_conversions( $start_date, $end_date, $flow_id = '' ) {
		global $wpdb;

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		// Format dates.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Adjust start date for analytics reset if specific flow.
		$start_date = wcf_pro_analytics()->get_adjusted_start_date( $flow_id, $start_date );

		if ( ! empty( $flow_id ) ) {
			// Single flow: Get conversion steps for this flow.
			$conversion_step_ids = wcf_pro_analytics()->get_flow_step_ids( $flow_id, true );

			if ( empty( $conversion_step_ids ) ) {
				return 0;
			}

			// Sanitize step IDs as integers to ensure SQL safety.
			$step_ids_str = implode( ', ', array_map( 'absint', $conversion_step_ids ) );

			// Build query with sanitized step IDs (absint above makes IN clause safe).
			$sql = "SELECT COUNT( DISTINCT {$visit_db}.id ) AS total_conversions
				FROM {$visit_db}
				INNER JOIN {$visit_meta_db} ON {$visit_db}.id = {$visit_meta_db}.visit_id
				WHERE {$visit_db}.step_id IN ( {$step_ids_str} )
				AND {$visit_meta_db}.meta_key = 'conversion'
				AND {$visit_meta_db}.meta_value = 'yes'
				AND {$visit_db}.date_visited BETWEEN %s AND %s";

			$query = $wpdb->prepare( $sql, $start_date, $end_date ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// All flows: Count conversions across all steps.
			$sql = "SELECT COUNT( DISTINCT {$visit_db}.id ) AS total_conversions
				FROM {$visit_db}
				INNER JOIN {$visit_meta_db} ON {$visit_db}.id = {$visit_meta_db}.visit_id
				WHERE {$visit_meta_db}.meta_key = 'conversion'
				AND {$visit_meta_db}.meta_value = 'yes'
				AND {$visit_db}.date_visited BETWEEN %s AND %s";

			$query = $wpdb->prepare( $sql, $start_date, $end_date ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $result ? (int) $result : 0;
	}

	/**
	 * Calculate conversions by date from visits/visits_meta tables.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $flow_id Flow ID (empty for all flows).
	 * @param array  $all_dates All dates in range.
	 * @return array Conversions by date.
	 */
	public function calculate_conversions_by_date( $start_date, $end_date, $flow_id, $all_dates ) {
		global $wpdb;

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		// Initialize array with zeros for all dates.
		$conversions_by_date = array();
		foreach ( $all_dates as $date ) {
			$conversions_by_date[ $date ] = 0;
		}

		// Format dates.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Adjust start date for analytics reset if specific flow.
		$start_date = wcf_pro_analytics()->get_adjusted_start_date( $flow_id, $start_date );

		if ( ! empty( $flow_id ) ) {
			// Single flow: Get conversion steps for this flow.
			$conversion_step_ids = wcf_pro_analytics()->get_flow_step_ids( $flow_id, true );

			if ( empty( $conversion_step_ids ) ) {
				return $conversions_by_date;
			}

			// Sanitize step IDs as integers to ensure SQL safety.
			$step_ids_str = implode( ', ', array_map( 'absint', $conversion_step_ids ) );

			// Build query with sanitized step IDs (absint above makes IN clause safe).
			$sql = "SELECT
				DATE_FORMAT( {$visit_db}.date_visited, '%%Y-%%m-%%d' ) AS visit_date,
				COUNT( DISTINCT {$visit_db}.id ) AS conversions
			FROM {$visit_db}
			INNER JOIN {$visit_meta_db} ON {$visit_db}.id = {$visit_meta_db}.visit_id
			WHERE {$visit_db}.step_id IN ( {$step_ids_str} )
			AND {$visit_meta_db}.meta_key = 'conversion'
			AND {$visit_meta_db}.meta_value = 'yes'
			AND {$visit_db}.date_visited BETWEEN %s AND %s
			GROUP BY visit_date";

			$query = $wpdb->prepare( $sql, $start_date, $end_date ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// All flows: Count conversions across all steps.
			$sql = "SELECT
				DATE_FORMAT( {$visit_db}.date_visited, '%%Y-%%m-%%d' ) AS visit_date,
				COUNT( DISTINCT {$visit_db}.id ) AS conversions
			FROM {$visit_db}
			INNER JOIN {$visit_meta_db} ON {$visit_db}.id = {$visit_meta_db}.visit_id
			WHERE {$visit_meta_db}.meta_key = 'conversion'
			AND {$visit_meta_db}.meta_value = 'yes'
			AND {$visit_db}.date_visited BETWEEN %s AND %s
			GROUP BY visit_date";

			$query = $wpdb->prepare( $sql, $start_date, $end_date ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$conversions_by_date[ $row->visit_date ] = (int) $row->conversions;
			}
		}

		return $conversions_by_date;
	}

	/**
	 * Calculate comparison metrics for the given date range.
	 * Returns only total metrics (no by_date data) for comparison purposes.
	 *
	 * @param string $start_date Comparison start date.
	 * @param string $end_date Comparison end date.
	 * @param string $screen_type Screen type ('funnels', 'conversions', or 'optins').
	 * @param string $flow_id Flow ID (empty for all flows).
	 * @return array Comparison metrics (totals only, no by_date).
	 */
	public function calculate_comparison_metrics( $start_date, $end_date, $screen_type, $flow_id = '' ) {

		// Handle optin analytics comparison.
		if ( 'optins' === $screen_type ) {
			return wcf_pro_analytics()->optin->get_optin_analytics( $start_date, $end_date, $flow_id, false );
		}

		// Initialize metrics.
		$metrics = wcf_pro_analytics()->initialize_metrics();

		// Get orders for the comparison date range.
		$orders = wcf_pro_analytics()->database->get_orders_by_flows( $start_date, $end_date, $flow_id );

		if ( empty( $orders ) || ! is_array( $orders ) ) {
			// Return zero metrics with formatted prices (screen-specific KPIs only).
			return wcf_pro_analytics()->format_comparison_metrics( $metrics, $screen_type );
		}

		// Get all dates in comparison range (needed for some calculations).
		$all_dates = wcf_pro_analytics()->get_date_range( $start_date, $end_date );

		// Initialize temporary date arrays (needed for processing but won't be returned).
		$temp_date_arrays = wcf_pro_analytics()->initialize_date_arrays( $all_dates );

		// Process orders.
		$metrics = $this->process_orders( $orders, $metrics, $temp_date_arrays );

		// Fetch and process visits.
		$visits_fetched = $this->fetch_visits_data( $flow_id, $start_date, $end_date, $orders, $screen_type );
		$metrics        = $this->process_visits( $visits_fetched, $metrics, $temp_date_arrays );

		// Calculate Revenue Per Unique Visitor.
		$rpuv_data                                 = $this->calculate_revenue_per_unique_visitor( $start_date, $end_date, $flow_id );
		$metrics['revenue_per_unique_visitor_raw'] = $rpuv_data['revenue_per_unique_visitor'];
		$metrics['total_unique_visitors']          = $rpuv_data['total_unique_visitors'];

		// Calculate Total Conversions.
		$metrics['total_conversions'] = $this->calculate_total_conversions( $start_date, $end_date, $flow_id );

		// Calculate derived metrics (AOV, RPV, etc.) - we need temp date arrays for this.
		$metrics = $this->calculate_derived_metrics( $metrics, $all_dates, $temp_date_arrays );

		// Format and return comparison metrics (screen-specific KPIs only).
		return wcf_pro_analytics()->format_comparison_metrics( $metrics, $screen_type );
	}

	/**
	 * Calculate derived metrics (AOV, RPV, etc.).
	 *
	 * @param array $metrics Metrics array.
	 * @param array $all_dates All dates in range.
	 * @param array $date_arrays Date-based arrays.
	 * @return array Updated metrics.
	 */
	public function calculate_derived_metrics( $metrics, $all_dates, &$date_arrays ) {

		// Calculate daily AOV and RPV for graphs.
		foreach ( $all_dates as $date ) {
			$daily_revenue = $date_arrays['revenue_by_date'][ $date ];
			$daily_orders  = $date_arrays['orders_by_date'][ $date ];
			$daily_visits  = $date_arrays['visits_by_date'][ $date ];

			if ( $daily_orders > 0 && $daily_revenue > 0 ) {
				$date_arrays['avg_order_value_by_date'][ $date ] = $daily_revenue / $daily_orders;
			}

			if ( $daily_visits > 0 && $daily_revenue > 0 ) {
				$date_arrays['revenue_per_visit_by_date'][ $date ] = $daily_revenue / $daily_visits;
			}
		}

		// Calculate overall AOV.
		if ( $metrics['total_orders'] > 0 ) {
			$metrics['avg_order_value'] = $metrics['total_revenue'] / $metrics['total_orders'];
		}

		// Calculate overall RPV.
		if ( $metrics['total_visits'] > 0 ) {
			$metrics['revenue_per_visit'] = $metrics['total_revenue'] / $metrics['total_visits'];
		}

		return $metrics;
	}

	// ============================================
	// RPUV Helper Methods (DRY: Don't Repeat Yourself)
	// ============================================

	/**
	 * Fetch RPUV order data (shared between total and by-date calculations).
	 * Eliminates 150+ lines of query duplication.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $flow_id Flow ID (empty for all flows).
	 * @param bool   $group_by_date Whether to include date grouping (for by-date calculations).
	 * @return array Query results with order data.
	 */
	private function fetch_rpuv_order_data( $start_date, $end_date, $flow_id = '', $group_by_date = false ) {
		global $wpdb;

		// Check for analytics reset date if specific flow.
		if ( ! empty( $flow_id ) ) {
			$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );
			if ( $analytics_reset_date > $start_date ) {
				$start_date = $analytics_reset_date;
			}
		}

		// Determine table structure based on HPOS.
		if ( wcf_pro_analytics()->database->is_custom_order_table_enabled() ) {
			// HPOS usage is enabled.
			$order_table         = $wpdb->prefix . 'wc_orders';
			$order_meta_table    = $wpdb->prefix . 'wc_orders_meta';
			$order_id_key        = 'order_id';
			$order_date_key      = 'date_created_gmt';
			$order_status_key    = 'status';
			$billing_email_field = 'tb1.billing_email';
			$order_total_field   = 'tb1.total_amount';
			$order_id_field      = 'tb1.id';
		} else {
			// Traditional CPT-based orders.
			$order_table         = $wpdb->prefix . 'posts';
			$order_meta_table    = $wpdb->prefix . 'postmeta';
			$order_id_key        = 'post_id';
			$order_date_key      = 'post_date';
			$order_status_key    = 'post_status';
			$billing_email_field = 'tb_email.meta_value';
			$order_total_field   = 'tb_total.meta_value';
			$order_id_field      = 'tb1.ID';
		}

		// Build query that gets BOTH parent and child orders.
		if ( wcf_pro_analytics()->database->is_custom_order_table_enabled() ) {
			// HPOS Query.
			$select_fields = $group_by_date
				? "DATE(tb1.{$order_date_key}) AS order_date, {$order_id_field} AS order_id"
				: "{$order_id_field} AS order_id";

			$query = "
				SELECT
					{$select_fields},
					{$billing_email_field} AS billing_email,
					{$order_total_field} AS order_total,
					tb_flow.meta_value AS flow_id,
					tb_parent_flow.meta_value AS parent_flow_id" .
					( $group_by_date ? ", MIN(tb1.{$order_date_key}) AS first_order_date" : '' ) . "
				FROM {$order_table} tb1
				LEFT JOIN {$order_meta_table} tb_flow
					ON tb1.id = tb_flow.{$order_id_key}
					AND tb_flow.meta_key = '_wcf_flow_id'
				LEFT JOIN {$order_meta_table} tb_parent_flow
					ON tb1.id = tb_parent_flow.{$order_id_key}
					AND tb_parent_flow.meta_key = '_cartflows_parent_flow_id'
				WHERE tb1.type = 'shop_order'
				AND tb1.{$order_status_key} IN ('wc-completed', 'wc-processing', 'wc-wcf-main-order')
				AND tb1.{$order_date_key} BETWEEN %s AND %s
				AND (tb_flow.meta_value IS NOT NULL OR tb_parent_flow.meta_value IS NOT NULL)
			";

			// Add flow filter if specific flow.
			if ( ! empty( $flow_id ) ) {
				$query .= $wpdb->prepare( ' AND (tb_flow.meta_value = %s OR tb_parent_flow.meta_value = %s)', $flow_id, $flow_id );
			}

			if ( $group_by_date ) {
				$query .= " GROUP BY order_date, {$order_id_field}, {$billing_email_field}";
				$query .= ' ORDER BY order_date ASC, first_order_date ASC';
			}

			$prepared_query = $wpdb->prepare( $query, $start_date, $end_date ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		} else {
			// Traditional Query.
			$select_fields = $group_by_date
				? "DATE(tb1.{$order_date_key}) AS order_date, {$order_id_field} AS order_id"
				: "{$order_id_field} AS order_id";

			$query = "
				SELECT
					{$select_fields},
					{$billing_email_field} AS billing_email,
					CAST({$order_total_field} AS DECIMAL(10,2)) AS order_total,
					tb_flow.meta_value AS flow_id,
					tb_parent_flow.meta_value AS parent_flow_id" .
					( $group_by_date ? ", MIN(tb1.{$order_date_key}) AS first_order_date" : '' ) . "
				FROM {$order_table} tb1
				LEFT JOIN {$order_meta_table} tb_flow
					ON tb1.ID = tb_flow.{$order_id_key}
					AND tb_flow.meta_key = '_wcf_flow_id'
				LEFT JOIN {$order_meta_table} tb_parent_flow
					ON tb1.ID = tb_parent_flow.{$order_id_key}
					AND tb_parent_flow.meta_key = '_cartflows_parent_flow_id'
				INNER JOIN {$order_meta_table} tb_email
					ON tb1.ID = tb_email.{$order_id_key}
					AND tb_email.meta_key = '_billing_email'
				INNER JOIN {$order_meta_table} tb_total
					ON tb1.ID = tb_total.{$order_id_key}
					AND tb_total.meta_key = '_order_total'
				WHERE tb1.post_type = 'shop_order'
				AND tb1.{$order_status_key} IN ('wc-completed', 'wc-processing', 'wc-wcf-main-order')
				AND tb1.{$order_date_key} BETWEEN %s AND %s
				AND (tb_flow.meta_value IS NOT NULL OR tb_parent_flow.meta_value IS NOT NULL)
			";

			// Add flow filter if specific flow.
			if ( ! empty( $flow_id ) ) {
				$query .= $wpdb->prepare( ' AND (tb_flow.meta_value = %s OR tb_parent_flow.meta_value = %s)', $flow_id, $flow_id );
			}

			if ( $group_by_date ) {
				$query .= " GROUP BY order_date, {$order_id_field}, {$billing_email_field}";
				$query .= ' ORDER BY order_date ASC, first_order_date ASC';
			}

			$prepared_query = $wpdb->prepare( $query, $start_date, $end_date ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Execute query and return results.
		return $wpdb->get_results( $prepared_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// ============================================
	// Metrics Processing Methods
	// ============================================

	/**
	 * Process orders and update metrics.
	 *
	 * @param array $orders Orders array (database rows with ID field).
	 * @param array $metrics Metrics array.
	 * @param array $date_arrays Date-based arrays.
	 * @return array Updated metrics.
	 */
	public function process_orders( $orders, $metrics, &$date_arrays ) {

		// Bulk fetch all WC_Order objects at once instead of calling wc_get_order() in loop.
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
			$order_date  = gmdate( 'Y-m-d', $date_created->getTimestamp() );
			$order_total = $order->get_total();

			// Increment total orders.
			++$metrics['total_orders'];

			// Track device conversions.
			$metrics = $this->track_device_conversions( $order, $metrics, $date_arrays, $order_date );

			// Add revenue if order is not cancelled.
			if ( ! $order->has_status( 'cancelled' ) ) {
				$metrics['total_revenue']                      += (float) $order_total;
				$date_arrays['revenue_by_date'][ $order_date ] += (float) $order_total;
			}

			// Add to orders by date graph.
			++$date_arrays['orders_by_date'][ $order_date ];

			// Process order items for bump offers and upsells/downsells.
			$metrics = $this->process_order_items( $order, $metrics, $date_arrays, $order_date );
		}

		return $metrics;
	}

	/**
	 * Process visits and update metrics.
	 *
	 * @param array $visits_fetched Visits data.
	 * @param array $metrics Metrics array.
	 * @param array $date_arrays Date-based arrays.
	 * @return array Updated metrics.
	 */
	public function process_visits( $visits_fetched, $metrics, &$date_arrays ) {

		if ( empty( $visits_fetched ) || ! is_array( $visits_fetched ) ) {
			return $metrics;
		}

		foreach ( $visits_fetched as $visit ) {
			if ( ! empty( $visit->total_visits ) ) {
				$total_visits = (int) $visit->total_visits;
				$date         = $visit->OrderDate; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

				$metrics['total_visits']                += $total_visits;
				$date_arrays['visits_by_date'][ $date ] += $total_visits;
			}
		}

		return $metrics;
	}

	/**
	 * Process order items for bump offers and upsells/downsells.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @return array Updated metrics.
	 */
	public function process_order_items( $order, $metrics, &$date_arrays, $order_date ) {
		$bump_product_id      = $order->get_meta( '_wcf_bump_product' );
		$multiple_obs         = $order->get_meta( '_wcf_bump_products' );
		$separate_offer_order = $order->get_meta( '_cartflows_parent_flow_id' );

		// Process merged orders (default behavior).
		if ( empty( $separate_offer_order ) ) {
			$metrics = $this->process_merged_order( $order, $metrics, $date_arrays, $order_date, $bump_product_id, $multiple_obs );
		} else {
			// Process separate offer orders.
			$metrics = $this->process_separate_offer_order( $order, $metrics, $date_arrays, $order_date );
		}

		return $metrics;
	}

	/**
	 * Process merged order items (when separate order is disabled).
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @param int      $bump_product_id Bump product ID.
	 * @param array    $multiple_obs Multiple order bumps.
	 * @return array Updated metrics.
	 */
	public function process_merged_order( $order, $metrics, &$date_arrays, $order_date, $bump_product_id, $multiple_obs ) {

		// Pre-check if bump product ID exists to skip unnecessary comparisons.
		$has_bump_product = ! empty( $bump_product_id );

		foreach ( $order->get_items() as $item_id => $item_data ) {
			$item_product_id = $item_data->get_product_id();
			$item_total      = $item_data->get_total();
			$is_upsell       = wc_get_order_item_meta( $item_id, '_cartflows_upsell', true );
			$is_downsell     = wc_get_order_item_meta( $item_id, '_cartflows_downsell', true );

			// Old single order bump - only check if bump product exists.
			if ( $has_bump_product && $item_product_id == $bump_product_id ) {
				$metrics['total_bump_revenue']                      += $item_total;
				$date_arrays['bump_revenue_by_date'][ $order_date ] += $item_total;
				++$metrics['total_bump_conversions'];
				++$date_arrays['bump_conversions_by_date'][ $order_date ];
			}

			// Upsell or Downsell.
			if ( 'yes' === $is_upsell || 'yes' === $is_downsell ) {
				$offer_revenue                                        = number_format( (float) $item_total, 2, '.', '' );
				$metrics['total_offers_revenue']                     += $offer_revenue;
				$date_arrays['offer_revenue_by_date'][ $order_date ] += $offer_revenue;
				++$metrics['total_offer_conversions'];
				++$date_arrays['offer_conversions_by_date'][ $order_date ];
			}
		}

		// Multiple order bumps.
		if ( is_array( $multiple_obs ) && ! empty( $multiple_obs ) ) {
			foreach ( $multiple_obs as $key => $data ) {
				$bump_price                     = number_format( $data['price'], wc_get_price_decimals(), '.', '' );
				$metrics['total_bump_revenue'] += $bump_price;
				$date_arrays['bump_revenue_by_date'][ $order_date ] += $bump_price;
				++$metrics['total_bump_conversions'];
				++$date_arrays['bump_conversions_by_date'][ $order_date ];
			}
		}

		return $metrics;
	}

	/**
	 * Process separate offer order (when separate order is enabled).
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @return array Updated metrics.
	 */
	public function process_separate_offer_order( $order, $metrics, &$date_arrays, $order_date ) {
		$is_offer    = $order->get_meta( '_cartflows_offer' );
		$order_total = $order->get_total();

		if ( 'yes' === $is_offer ) {
			$offer_revenue                                        = number_format( (float) $order_total, 2, '.', '' );
			$metrics['total_offers_revenue']                     += $offer_revenue;
			$date_arrays['offer_revenue_by_date'][ $order_date ] += $offer_revenue;
			++$metrics['total_offer_conversions'];
			++$date_arrays['offer_conversions_by_date'][ $order_date ];
		}

		return $metrics;
	}

	/**
	 * Track device-based conversions.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $metrics Metrics array.
	 * @param array    $date_arrays Date-based arrays.
	 * @param string   $order_date Order date.
	 * @return array Updated metrics.
	 */
	public function track_device_conversions( $order, $metrics, &$date_arrays, $order_date ) {
		$device_type = strtolower( (string) $order->get_meta( '_wc_order_attribution_device_type' ) );

		$device_map = array(
			'mobile'  => 'mobile_conversions',
			'desktop' => 'desktop_conversions',
		);

		if ( isset( $device_map[ $device_type ] ) ) {
			$key = $device_map[ $device_type ];
			++$metrics[ 'total_' . $key ];
			++$date_arrays[ $key . '_by_date' ][ $order_date ];
		}

		return $metrics;
	}

	/**
	 * Build step analytics data structure.
	 * Maps visit data and earnings to flow steps, handles AB tests and archived variations.
	 *
	 * @param int   $flow_id Flow ID.
	 * @param array $visits Visit data from database.
	 * @param array $earning Earning data (checkout/offer revenue).
	 * @return array Steps array with analytics data attached.
	 */
	public function build_steps_with_analytics( $flow_id, $visits, $earning ) {

		$visits_map = array();

		foreach ( $visits as $v_in => $v_data ) {

			$step_id                = $v_data->step_id;
			$v_data_array           = (array) $v_data;
			$visits_map[ $step_id ] = $v_data_array;
			$step_type              = wcf()->utils->get_step_type( $step_id );

			$visits_map[ $step_id ]['revenue']         = 0;
			$visits_map[ $step_id ]['title']           = get_the_title( $step_id );
			$visits_map[ $step_id ]['note']            = get_post_meta( $step_id, 'wcf-step-note', true );
			$visits_map[ $step_id ]['conversion_rate'] = 0;

			// Set conversion rate.
			$conversions  = intval( $v_data_array['conversions'] );
			$total_visits = intval( $v_data_array['total_visits'] );

			if ( $total_visits > 0 ) {

				$conversion_rate = $conversions / intval( $v_data_array['total_visits'] ) * 100;

				$visits_map[ $step_id ]['conversion_rate'] = number_format( (float) $conversion_rate, 2, '.', '' );
			}

			switch ( $step_type ) {

				case 'checkout':
					$visits_map[ $step_id ]['revenue'] = 0;

					if ( isset( $earning['checkout'][ $step_id ] ) ) {
						$visits_map[ $step_id ]['revenue'] = $earning['checkout'][ $step_id ];
					}
					break;
				case 'upsell':
				case 'downsell':
					$visits_map[ $step_id ]['revenue'] = 0;

					if ( isset( $earning['offer'][ $step_id ] ) ) {
						$visits_map[ $step_id ]['revenue'] = $earning['offer'][ $step_id ];
					}
					break;
			}

			$visits_map[ $step_id ]['revenue'] = number_format( (float) $visits_map[ $step_id ]['revenue'], 2, '.', '' );
		}

		$all_steps = wcf()->flow->get_steps( $flow_id );

		foreach ( $all_steps as $in => $step_data ) {

			$step_id = $step_data['id'];

			if ( isset( $visits_map[ $step_id ] ) ) {

				$all_steps[ $in ]['visits'] = $visits_map[ $step_id ];

				if ( isset( $step_data['ab-test'] ) ) {

					$ab_total_visits  = 0;
					$ab_unique_visits = 0;
					$ab_conversions   = 0;
					$ab_revenue       = 0;

					// If ab test true but ab test ui is off and variations are empty.
					if ( isset( $step_data['ab-test-variations'] ) && ! empty( $step_data['ab-test-variations'] ) ) {

						$variations = $step_data['ab-test-variations'];

						foreach ( $variations as $v_in => $v_data ) {

							$v_id = $v_data['id'];

							if ( isset( $visits_map[ $v_id ] ) ) {

								$all_steps[ $in ]['visits-ab'][ $v_id ] = $visits_map[ $v_id ];

								$ab_total_visits  = $ab_total_visits + intval( $visits_map[ $v_id ]['total_visits'] );
								$ab_unique_visits = $ab_unique_visits + intval( $visits_map[ $v_id ]['unique_visits'] );
								$ab_conversions   = $ab_conversions + intval( $visits_map[ $v_id ]['conversions'] );
								$ab_revenue       = $ab_revenue + $visits_map[ $v_id ]['revenue'];

							}
						}
					} else {
						$ab_total_visits  = $all_steps[ $in ]['visits']['total_visits'];
						$ab_unique_visits = $all_steps[ $in ]['visits']['unique_visits'];
						$ab_conversions   = $all_steps[ $in ]['visits']['conversions'];
						$ab_revenue       = $all_steps[ $in ]['visits']['revenue'];

						$all_steps[ $in ]['visits-ab'][ $step_id ] = $visits_map[ $step_id ];
					}

					if ( isset( $step_data['ab-test-archived-variations'] ) && ! empty( $step_data['ab-test-archived-variations'] ) ) {

						/* Add archived variations */
						$archived_variations = $step_data['ab-test-archived-variations'];

						foreach ( $archived_variations as $v_in => $v_data ) {

							$v_id = $v_data['id'];

							if ( isset( $visits_map[ $v_id ] ) ) {

								$all_steps[ $in ]['visits-ab-archived'][ $v_id ]          = $visits_map[ $v_id ];
								$all_steps[ $in ]['visits-ab-archived'][ $v_id ]['title'] = $v_data['title'];

								if ( $v_data['deleted'] ) {
									$all_steps[ $in ]['visits-ab-archived'][ $v_id ]['archived_date'] = '(Deleted on ' . $v_data['date'] . ')';
								} else {
									$all_steps[ $in ]['visits-ab-archived'][ $v_id ]['archived_date'] = '(Archived on ' . $v_data['date'] . ')';
								}

								$all_steps[ $in ]['visits-ab-archived'][ $v_id ]['note'] = isset( $v_data['note'] ) ? $v_data['note'] : '';

								$ab_total_visits  = $ab_total_visits + intval( $visits_map[ $v_id ]['total_visits'] );
								$ab_unique_visits = $ab_unique_visits + intval( $visits_map[ $v_id ]['unique_visits'] );
								$ab_conversions   = $ab_conversions + intval( $visits_map[ $v_id ]['conversions'] );
								$ab_revenue       = $ab_revenue + $visits_map[ $v_id ]['revenue'];
							}
						}
					}

					// Add total count to main step.
					$all_steps[ $in ]['visits']['total_visits']  = $ab_total_visits;
					$all_steps[ $in ]['visits']['unique_visits'] = $ab_unique_visits;
					$all_steps[ $in ]['visits']['conversions']   = $ab_conversions;
					$all_steps[ $in ]['visits']['revenue']       = wcf_pro_analytics()->format_price( $ab_revenue );

					// Calculate total conversion count and set to main step.
					$total_conversion_rate = 0;

					if ( $ab_total_visits > 0 ) {
						$total_conversion_rate = $ab_conversions / $ab_total_visits * 100;
						$total_conversion_rate = number_format( (float) $total_conversion_rate, 2, '.', '' );
					}

					$all_steps[ $in ]['visits']['conversion_rate'] = $total_conversion_rate;
				} else {
					$all_steps[ $in ]['visits']['revenue'] = wcf_pro_analytics()->format_price( $all_steps[ $in ]['visits']['revenue'] );
				}
			}
		}

		return $all_steps;
	}

	/**
	 * Fetch visits data based on flow context.
	 *
	 * @param int    $dashboard_flow_id Flow ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param array  $orders Orders array.
	 * @param string $screen_type Screen type ('funnels', 'conversions', or 'optins').
	 * @return array Visits data.
	 */
	public function fetch_visits_data( $dashboard_flow_id, $start_date, $end_date, $orders, $screen_type ) {

		if ( ! empty( $dashboard_flow_id ) ) {
			return wcf_pro_analytics()->database->fetch_visits( $dashboard_flow_id, $start_date, $end_date, $screen_type );
		}

		// Get Flow IDs from orders and calculate visits.
		$flow_ids = array_column( $orders, 'meta_value' );
		return wcf_pro_analytics()->database->fetch_visits_of_all_flows( $flow_ids, $start_date, $end_date );
	}
}

Cartflows_Pro_Analytics_Calculations::get_instance();

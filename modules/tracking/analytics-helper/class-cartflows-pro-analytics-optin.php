<?php
/**
 * Analytics Optin
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Analytics optin class.
 * Handles all optin-specific analytics operations.
 */
class Cartflows_Pro_Analytics_Optin {

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
	 * Get optin analytics data.
	 * Main entry point for optin analytics screen.
	 *
	 * @param array  $analytics_data Initial analytics data.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @param string $dashboard_flow_id Flow ID (empty for all flows).
	 * @param string $comparison_range_type Comparison range type.
	 * @return array Complete optin analytics data.
	 */
	public function get_analytics_data( $analytics_data, $start_date, $end_date, $dashboard_flow_id = '', $comparison_range_type = '' ) {

		// Get optin analytics data.
		$optin_data = $this->get_optin_analytics( $start_date, $end_date, $dashboard_flow_id );

		// Get optin flow conversions data.
		$analytics_data['optin_flow_conversions'] = $this->calculate_optin_flows_analytics( $start_date, $end_date );

		// Add comparison data if requested.
		if ( ! empty( $comparison_range_type ) ) {
			$comparison_dates = wcf_pro_analytics()->calculate_comparison_dates( $start_date, $end_date, $comparison_range_type );

			$comparison_start_date = $comparison_dates['comparison_start_date'];
			$comparison_end_date   = $comparison_dates['comparison_end_date'];

			if ( ! empty( $comparison_start_date ) && ! empty( $comparison_end_date ) ) {
				$analytics_data['comparison'] = wcf_pro_analytics()->calculations->calculate_comparison_metrics(
					$comparison_start_date,
					$comparison_end_date,
					'optins',
					$dashboard_flow_id
				);
			} else {
				$analytics_data['comparison'] = array();
			}
		} else {
			$analytics_data['comparison'] = array();
		}

		// Merge optin data with analytics data.
		return array_merge( $analytics_data, $optin_data );
	}

	/**
	 * Get opt-in analytics data for flows.
	 *
	 * @param string $start_date Start date for analytics.
	 * @param string $end_date End date for analytics.
	 * @param string $flow_id Optional. Specific flow ID. If empty, gets data for all flows.
	 * @param bool   $include_by_date Whether to include by_date arrays (false for comparison).
	 * @return array Opt-in analytics data.
	 */
	public function get_optin_analytics( $start_date, $end_date, $flow_id = '', $include_by_date = true ) {
		global $wpdb;

		// Validate dates - return empty data if invalid.
		if ( empty( $start_date ) || empty( $end_date ) ) {
			$empty_data = array(
				'total_visits'      => 0,
				'total_submissions' => 0,
				'conversion_rate'   => '0.00',
			);

			// Include by_date arrays only if requested.
			if ( $include_by_date ) {
				$empty_data['visits_by_date']          = array();
				$empty_data['conversion_rate_by_date'] = array();
			}

			return $empty_data;
		}

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		// Format dates.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Get all dates in range for initialization.
		$all_dates = wcf_pro_analytics()->get_date_range( $start_date, $end_date );

		// Initialize result arrays.
		$optin_submissions_by_date     = array();
		$optin_conversion_rate_by_date = array();
		$optin_conversions_by_date     = array();

		foreach ( $all_dates as $date ) {
			$optin_submissions_by_date[ $date ]     = 0;
			$optin_conversion_rate_by_date[ $date ] = 0;
			$optin_conversions_by_date[ $date ]     = 0;
		}

		// Get opt-in steps based on flow_id.
		$optin_steps = $this->get_optin_steps( $flow_id );

		if ( empty( $optin_steps ) ) {
			$empty_data = array(
				'total_visits'      => 0,
				'total_submissions' => 0,
				'conversion_rate'   => '0.00',
			);

			// Include by_date arrays only if requested.
			if ( $include_by_date ) {
				$empty_data['visits_by_date']          = array();
				$empty_data['conversion_rate_by_date'] = array();
			}

			return $empty_data;
		}

		// Sanitize step IDs as integers to ensure SQL safety.
		$step_ids = implode( ', ', array_map( 'absint', $optin_steps ) );

		// Check for analytics reset date if specific flow.
		if ( ! empty( $flow_id ) ) {
			$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );

			if ( $analytics_reset_date > $start_date ) {
				$start_date = $analytics_reset_date;
			}
		}

		// Query to get opt-in visits and conversions by date.
		// Step IDs are sanitized via absint() above, making the IN clause safe.
		// Table names cannot be parameterized - they must be interpolated.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT
				DATE_FORMAT( $visit_db.date_visited, '%%Y-%%m-%%d') AS visit_date,
				COUNT( DISTINCT( $visit_db.id ) ) AS total_visits,
				COUNT( CASE WHEN $visit_meta_db.meta_key = 'conversion'
					AND $visit_meta_db.meta_value = 'yes'
					THEN $visit_db.id ELSE NULL END ) AS conversions
			FROM $visit_db
			INNER JOIN $visit_meta_db ON $visit_db.id = $visit_meta_db.visit_id
			WHERE $visit_db.step_id IN ( $step_ids )
			AND ( $visit_db.date_visited BETWEEN %s AND %s )
			GROUP BY visit_date
			ORDER BY visit_date ASC",
			$start_date,
			$end_date
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// Initialize totals.
		$total_visits      = 0;
		$total_submissions = 0;

		// Process results and populate by-date arrays.
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$date        = $row->visit_date;
				$visits      = intval( $row->total_visits );
				$conversions = intval( $row->conversions );

				$optin_submissions_by_date[ $date ] = $visits;
				$optin_conversions_by_date[ $date ] = $conversions;

				// Calculate conversion rate for this date.
				if ( $visits > 0 ) {
					$daily_rate                             = ( $conversions / $visits ) * 100;
					$optin_conversion_rate_by_date[ $date ] = number_format( (float) $daily_rate, 2, '.', '' );
				}

				$total_visits      += $visits;
				$total_submissions += $conversions;
			}
		}

		// Calculate overall conversion rate.
		$conversion_rate = '0.00';
		if ( $total_visits > 0 ) {
			$conversion_rate = number_format( ( $total_submissions / $total_visits ) * 100, 2, '.', '' );
		}

		// Build result array with totals.
		$result = array(
			'total_visits'      => $total_visits,
			'total_submissions' => $total_submissions,
			'conversion_rate'   => $conversion_rate,
		);

		// Include by_date arrays only if requested.
		if ( $include_by_date ) {
			$submissions_by_date_cleaned       = wcf_pro_analytics()->remove_zero_values( $optin_conversions_by_date, 'submissions' );
			$conversion_rate_by_date_cleaned   = wcf_pro_analytics()->remove_zero_values( $optin_conversion_rate_by_date, 'conversions' );
			$result['submissions_by_date']     = $submissions_by_date_cleaned;
			$result['conversion_rate_by_date'] = $conversion_rate_by_date_cleaned;
		}

		return $result;
	}

	/**
	 * Get all opt-in step IDs for a flow or all flows.
	 *
	 * @param string $flow_id Optional. Specific flow ID. If empty, gets steps from all flows.
	 * @return array Array of opt-in step IDs.
	 */
	public function get_optin_steps( $flow_id = '' ) {
		$optin_steps = array();

		if ( ! empty( $flow_id ) ) {
			// Get steps for specific flow.
			$steps       = wcf()->flow->get_steps( $flow_id );
			$optin_steps = $this->extract_optin_steps_from_flow( $steps );
		} else {
			// Get all published flows.
			$args = array(
				'post_type'      => CARTFLOWS_FLOW_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);

			$flow_ids = get_posts( $args );

			if ( ! empty( $flow_ids ) ) {
				foreach ( $flow_ids as $fid ) {
					$steps            = wcf()->flow->get_steps( $fid );
					$flow_optin_steps = $this->extract_optin_steps_from_flow( $steps );
					$optin_steps      = array_merge( $optin_steps, $flow_optin_steps );
				}
			}
		}

		return array_unique( $optin_steps );
	}

	/**
	 * Extract opt-in step IDs from flow steps array.
	 * Handles AB test variations and archived variations.
	 *
	 * @param array $steps Flow steps array.
	 * @return array Array of opt-in step IDs.
	 */
	public function extract_optin_steps_from_flow( $steps ) {
		$optin_steps = array();

		foreach ( $steps as $step_data ) {
			$step_id   = $step_data['id'];
			$step_type = wcf()->utils->get_step_type( $step_id );

			if ( 'optin' === $step_type ) {
				// Check if this is an AB test.
				if ( isset( $step_data['ab-test'] ) ) {
					// Add AB test variations.
					if ( isset( $step_data['ab-test-variations'] ) && ! empty( $step_data['ab-test-variations'] ) ) {
						foreach ( $step_data['ab-test-variations'] as $variation ) {
							$variation_type = wcf()->utils->get_step_type( $variation['id'] );
							if ( 'optin' === $variation_type ) {
								$optin_steps[] = $variation['id'];
							}
						}
					} else {
						$optin_steps[] = $step_id;
					}

					// Add archived variations.
					if ( isset( $step_data['ab-test-archived-variations'] ) && ! empty( $step_data['ab-test-archived-variations'] ) ) {
						foreach ( $step_data['ab-test-archived-variations'] as $archived_variation ) {
							$archived_type = wcf()->utils->get_step_type( $archived_variation['id'] );
							if ( 'optin' === $archived_type ) {
								$optin_steps[] = $archived_variation['id'];
							}
						}
					}
				} else {
					// Regular opt-in step (no AB test).
					$optin_steps[] = $step_id;
				}
			}
		}

		return $optin_steps;
	}

	/**
	 * Calculate optin analytics for all flows with optin steps in the given date range.
	 *
	 * @param string $start_date start date.
	 * @param string $end_date end date.
	 * @return array Array containing optin analytics data for flows with optin steps.
	 */
	public function calculate_optin_flows_analytics( $start_date, $end_date ) {
		global $wpdb;

		$optin_flows_analytics = array();

		// Get all published flows.
		$flows = get_posts(
			array(
				'post_type'      => CARTFLOWS_FLOW_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $flows ) ) {
			return array();
		}

		$visit_db      = $wpdb->prefix . CARTFLOWS_PRO_VISITS_TABLE;
		$visit_meta_db = $wpdb->prefix . CARTFLOWS_PRO_VISITS_META_TABLE;

		// Format dates.
		$start_date = wcf_pro_analytics()->format_date_time( $start_date, false );
		$end_date   = wcf_pro_analytics()->format_date_time( $end_date, true );

		// Process each flow.
		foreach ( $flows as $flow_id ) {

			// Check analytics reset date.
			$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );
			$flow_start_date      = $start_date;

			if ( $analytics_reset_date > $start_date ) {
				$flow_start_date = $analytics_reset_date;
			}

			// Get all steps for this flow.
			$steps            = wcf()->flow->get_steps( $flow_id );
			$optin_steps_data = array();

			// Extract optin steps including AB test variations.
			foreach ( $steps as $step_data ) {
				$step_id   = $step_data['id'];
				$step_type = wcf()->utils->get_step_type( $step_id );

				if ( 'optin' === $step_type ) {

					// Check if this is an AB test.
					if ( isset( $step_data['ab-test'] ) ) {

						// Add AB test variations.
						if ( isset( $step_data['ab-test-variations'] ) && ! empty( $step_data['ab-test-variations'] ) ) {
							foreach ( $step_data['ab-test-variations'] as $variation ) {
								$variation_type = wcf()->utils->get_step_type( $variation['id'] );
								if ( 'optin' === $variation_type ) {
									$optin_steps_data[] = array(
										'step_id'      => $variation['id'],
										'step_title'   => get_the_title( $variation['id'] ),
										'is_variation' => true,
									);
								}
							}
						} else {
							// AB test enabled but no variations yet.
							$optin_steps_data[] = array(
								'step_id'      => $step_id,
								'step_title'   => get_the_title( $step_id ),
								'is_variation' => false,
							);
						}

						// Add archived variations.
						if ( isset( $step_data['ab-test-archived-variations'] ) && ! empty( $step_data['ab-test-archived-variations'] ) ) {
							foreach ( $step_data['ab-test-archived-variations'] as $archived_variation ) {
								$archived_type = wcf()->utils->get_step_type( $archived_variation['id'] );
								if ( 'optin' === $archived_type ) {
									$archived_title = isset( $archived_variation['title'] ) ? $archived_variation['title'] : get_the_title( $archived_variation['id'] );
									$archived_date  = isset( $archived_variation['date'] ) ? $archived_variation['date'] : '';
									$is_deleted     = isset( $archived_variation['deleted'] ) ? $archived_variation['deleted'] : false;

									$archive_label = $is_deleted ? ' (Deleted on ' . $archived_date . ')' : ' (Archived on ' . $archived_date . ')';

									$optin_steps_data[] = array(
										'step_id'      => $archived_variation['id'],
										'step_title'   => $archived_title . $archive_label,
										'is_variation' => true,
										'is_archived'  => true,
									);
								}
							}
						}
					} else {
						// Regular optin step (no AB test).
						$optin_steps_data[] = array(
							'step_id'      => $step_id,
							'step_title'   => get_the_title( $step_id ),
							'is_variation' => false,
						);
					}
				}
			}

			// Skip flows with no optin steps.
			if ( empty( $optin_steps_data ) ) {
				continue;
			}

			$flow_title = get_the_title( $flow_id );

			// Process each optin step.
			foreach ( $optin_steps_data as $optin_step ) {

				$step_id = $optin_step['step_id'];

				// Query to get visits and conversions for this specific optin step.
				// phpcs:disable WordPress.DB.PreparedSQL
				$query = $wpdb->prepare(
					"SELECT
						COUNT( DISTINCT( $visit_db.id ) ) AS total_visits,
						COUNT( CASE WHEN $visit_meta_db.meta_key = 'conversion'
							AND $visit_meta_db.meta_value = 'yes'
							THEN $visit_db.id ELSE NULL END ) AS conversions
					FROM $visit_db
					INNER JOIN $visit_meta_db ON $visit_db.id = $visit_meta_db.visit_id
					WHERE $visit_db.step_id = %d
					AND ( $visit_db.date_visited BETWEEN %s AND %s )",
					$step_id,
					$flow_start_date,
					$end_date
				);

				$result = $wpdb->get_row( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery
				// phpcs:enable WordPress.DB.PreparedSQL

				$total_visits    = 0;
				$conversions     = 0;
				$conversion_rate = '0.00';

				if ( $result ) {
					$total_visits = (int) $result->total_visits;
					$conversions  = (int) $result->conversions;

					// Calculate conversion rate.
					if ( $total_visits > 0 ) {
						$conversion_rate = ( $conversions / $total_visits ) * 100;
						$conversion_rate = number_format( (float) $conversion_rate, 2, '.', '' );
					}
				}

				// Add to analytics array.
				$optin_flows_analytics[] = array(
					'flow_id'         => $flow_id,
					'flow_title'      => $flow_title,
					'step_id'         => $step_id,
					'step_title'      => $optin_step['step_title'],
					'optin_form'      => $conversions, // Total conversions for this step.
					'popup'           => 0, // Placeholder for future implementation.
					'lead_magnets'    => 0, // Placeholder for future implementation.
					'conversion_rate' => $conversion_rate,
					'date_time'       => 0, // Placeholder for future implementation.
				);
			}
		}

		return $optin_flows_analytics;
	}
}

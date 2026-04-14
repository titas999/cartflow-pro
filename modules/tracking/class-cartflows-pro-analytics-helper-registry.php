<?php
/**
 * Analytics Helper Registry
 *
 * Provides centralized access to all analytics helper class instances.
 * Similar to wcf_pro() pattern for consistent architecture.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Cartflows_Pro_Analytics_Helper_Registry' ) ) {

	/**
	 * Analytics Helper Registry class.
	 * Manages and provides access to all analytics helper instances.
	 */
	final class Cartflows_Pro_Analytics_Helper_Registry {

		/**
		 * Member Variable
		 *
		 * @var object instance
		 */
		private static $instance;

		/**
		 * Database helper instance
		 *
		 * @var Cartflows_Pro_Analytics_Db
		 */
		public $database = null;


		/**
		 * Calculations helper instance
		 *
		 * @var Cartflows_Pro_Analytics_Calculations
		 */
		public $calculations = null;

		/**
		 * Optin helper instance
		 *
		 * @var Cartflows_Pro_Analytics_Optin
		 */
		public $optin = null;

		/**
		 * Products helper instance
		 *
		 * @var Cartflows_Pro_Analytics_Products
		 */
		public $products = null;

		/**
		 * Conversions helper instance
		 *
		 * @var Cartflows_Pro_Analytics_Conversions
		 */
		public $conversions = null;

		/**
		 * Funnels helper instance
		 *
		 * @var Cartflows_Pro_Analytics_Funnels
		 */
		public $funnels = null;

		/**
		 * Initiator
		 *
		 * @since 2.2.0
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->database     = Cartflows_Pro_Analytics_Db::get_instance();
			$this->calculations = Cartflows_Pro_Analytics_Calculations::get_instance();
			$this->optin        = Cartflows_Pro_Analytics_Optin::get_instance();
			$this->products     = Cartflows_Pro_Analytics_Products::get_instance();
			$this->conversions  = Cartflows_Pro_Analytics_Conversions::get_instance();
			$this->funnels      = Cartflows_Pro_Analytics_Funnels::get_instance();
		}

		// ============================================
		// Utility Methods (Facade Pattern)
		// ============================================

		/**
		 * Initialize base metrics structure.
		 *
		 * @return array
		 */
		public function initialize_metrics() {
			return array(
				'total_revenue'              => 0,
				'total_orders'               => 0,
				'total_bump_revenue'         => 0,
				'total_bump_conversions'     => 0,
				'total_offers_revenue'       => 0,
				'total_offer_conversions'    => 0,
				'total_visits'               => 0,
				'total_conversions'          => 0,
				'avg_order_value'            => 0,
				'revenue_per_visit'          => 0,
				'total_mobile_conversions'   => 0,
				'total_desktop_conversions'  => 0,
				'optin_visits'               => 0,
				'optin_conversion_rate'      => 0,
				'optin_list_growth'          => 0,
				'revenue_per_unique_visitor' => 0,
				'total_unique_visitors'      => 0,
			);
		}

		/**
		 * Initialize date-based arrays for graphs.
		 *
		 * @param array $all_dates Array of dates.
		 * @return array
		 */
		public function initialize_date_arrays( $all_dates ) {
			// Use array_fill_keys for faster initialization.
			// This is more efficient than nested loops for initializing date arrays.
			$date_defaults = array_fill_keys( $all_dates, 0 );

			$arrays = array(
				'visits_by_date'                     => $date_defaults,
				'orders_by_date'                     => $date_defaults,
				'conversions_by_date'                => $date_defaults,
				'revenue_by_date'                    => $date_defaults,
				'avg_order_value_by_date'            => $date_defaults,
				'bump_revenue_by_date'               => $date_defaults,
				'bump_conversions_by_date'           => $date_defaults,
				'revenue_per_visit_by_date'          => $date_defaults,
				'offer_revenue_by_date'              => $date_defaults,
				'mobile_conversions_by_date'         => $date_defaults,
				'offer_conversions_by_date'          => $date_defaults,
				'desktop_conversions_by_date'        => $date_defaults,
				'revenue_per_unique_visitor_by_date' => $date_defaults,
			);

			return $arrays;
		}

		/**
		 * Prepare array of the dates for the date range.
		 *
		 * @param string $start_date Start Date.
		 * @param string $end_date End Date.
		 * @return array
		 */
		public function get_date_range( $start_date, $end_date ) {
			$dates   = array();
			$current = strtotime( $start_date );
			$end     = strtotime( $end_date );

			while ( $current <= $end ) {
				$dates[] = gmdate( 'Y-m-d', (int) $current );
				$current = strtotime( '+1 day', (int) $current );
			}

			return $dates;
		}

		/**
		 * Ensure date has time component for SQL queries.
		 * Converts Y-m-d to Y-m-d H:i:s format.
		 *
		 * @param string $date Date string (Y-m-d or Y-m-d H:i:s).
		 * @param bool   $is_end_date Whether this is end of day (23:59:59) or start (00:00:00).
		 * @return string Date with time component.
		 */
		public function format_date_time( $date, $is_end_date = false ) {
			if ( 10 === strlen( $date ) ) {
				$time_suffix = $is_end_date ? ' 23:59:59' : ' 00:00:00';
				return gmdate( 'Y-m-d H:i:s', (int) strtotime( $date . $time_suffix ) );
			}
			return $date;
		}

		/**
		 * Get adjusted start date based on analytics reset.
		 *
		 * @param int|string $flow_id Flow ID.
		 * @param string     $start_date Original start date.
		 * @return string Adjusted start date.
		 */
		public function get_adjusted_start_date( $flow_id, $start_date ) {
			if ( empty( $flow_id ) ) {
				return $start_date;
			}

			$analytics_reset_date = wcf()->options->get_flow_meta_value( $flow_id, 'wcf-analytics-reset-date' );

			if ( $analytics_reset_date > $start_date ) {
				return $analytics_reset_date;
			}

			return $start_date;
		}

		/**
		 * Get all step IDs for a flow including AB test variations.
		 *
		 * @param int  $flow_id Flow ID.
		 * @param bool $conversion_steps_only Only return steps that can have conversions.
		 * @return array Step IDs.
		 */
		public function get_flow_step_ids( $flow_id, $conversion_steps_only = false ) {
			$step_ids = array();
			$steps    = wcf()->flow->get_steps( $flow_id );

			foreach ( $steps as $step_data ) {
				$step_id        = $step_data['id'];
				$main_step_type = wcf()->utils->get_step_type( $step_id );
				$can_convert    = ( 'thankyou' !== $main_step_type );

				if ( ! $conversion_steps_only || $can_convert ) {
					$step_ids[] = $step_id;
				}

				// Handle AB test variations.
				if ( isset( $step_data['ab-test-variations'] ) && ! empty( $step_data['ab-test-variations'] ) ) {
					foreach ( $step_data['ab-test-variations'] as $variation ) {
						$variation_id    = $variation['id'];
						$variation_type  = wcf()->utils->get_step_type( $variation_id );
						$var_can_convert = ( 'thankyou' !== $variation_type );

						if ( ! $conversion_steps_only || $var_can_convert ) {
							$step_ids[] = $variation_id;
						}
					}
				}

				// Handle archived variations.
				if ( isset( $step_data['ab-test-archived-variations'] ) && ! empty( $step_data['ab-test-archived-variations'] ) ) {
					foreach ( $step_data['ab-test-archived-variations'] as $archived ) {
						$archived_id      = $archived['id'];
						$archived_type    = wcf()->utils->get_step_type( $archived_id );
						$arch_can_convert = ( 'thankyou' !== $archived_type );

						if ( ! $conversion_steps_only || $arch_can_convert ) {
							$step_ids[] = $archived_id;
						}
					}
				}
			}

			return array_unique( $step_ids );
		}

		/**
		 * Format price with currency symbol.
		 *
		 * @param float|int|string $value Price value.
		 * @return string Formatted price.
		 */
		public function format_price( $value ) {
			return str_replace( '&nbsp;', '', wc_price( (float) $value ) );
		}

		/**
		 * Extract the price from the HTML
		 *
		 * @param string $html HTML content.
		 * @return float
		 */
		public function extract_price( $html ) {
			// Remove HTML tags.
			$clean = wp_strip_all_tags( (string) $html );

			// Get WooCommerce decimal and thousand separators.
			$decimal_sep  = wc_get_price_decimal_separator();
			$thousand_sep = wc_get_price_thousand_separator();

			// Remove currency symbols and whitespace.
			$clean = (string) preg_replace( '/[^\d' . preg_quote( $decimal_sep, '/' ) . preg_quote( $thousand_sep, '/' ) . ']/', '', $clean );

			// Remove thousands separator and normalize decimal separator.
			$clean = str_replace( $thousand_sep, '', $clean );
			$clean = str_replace( $decimal_sep, '.', $clean );

			return (float) $clean;
		}

		/**
		 * Remove the zero visits from per day data to avoid clutter.
		 *
		 * @param array  $data_per_day Data per day.
		 * @param string $key Key.
		 * @return array
		 */
		public function remove_zero_values( $data_per_day, $key = '' ) {
			$updated_data_per_day = array();
			foreach ( $data_per_day as $date => $value ) {
				if ( empty( $value ) || 0 === (int) $value ) {
					continue; // Skip zero visits.
				}

				$updated_data_per_day[] = array(
					'OrderDate' => $date,
					$key        => $value,
				);
			}

			return $updated_data_per_day;
		}

		/**
		 * Format comparison metrics based on screen type.
		 * Returns only the KPIs needed for each screen.
		 *
		 * @param array  $metrics Raw metrics array.
		 * @param string $screen_type Screen type ('funnels' or 'conversions').
		 * @return array Formatted comparison metrics (totals only, no by_date).
		 */
		public function format_comparison_metrics( $metrics, $screen_type = 'funnels' ) {
			// Funnels screen KPIs.
			if ( 'funnels' === $screen_type ) {
				return array(
					'total_revenue_raw'              => $metrics['total_revenue'],
					'total_orders'                   => $metrics['total_orders'],
					'total_bump_revenue_raw'         => $metrics['total_bump_revenue'],
					'total_offers_revenue_raw'       => $metrics['total_offers_revenue'],
					'total_visits'                   => $metrics['total_visits'],
					'avg_order_value_raw'            => $metrics['avg_order_value'],
					'revenue_per_visit_raw'          => $metrics['revenue_per_visit'],
					'revenue_per_unique_visitor_raw' => $metrics['revenue_per_unique_visitor'],
					'total_unique_visitors'          => $metrics['total_unique_visitors'],
				);
			}

			// Conversions screen KPIs.
			return array(
				'total_visits'              => $metrics['total_visits'],
				'total_conversions'         => $metrics['total_conversions'],
				'total_bump_conversions'    => $metrics['total_bump_conversions'],
				'total_offer_conversions'   => $metrics['total_offer_conversions'],
				'total_mobile_conversions'  => $metrics['total_mobile_conversions'],
				'total_desktop_conversions' => $metrics['total_desktop_conversions'],
			);
		}

		/**
		 * Calculate comparison period dates.
		 *
		 * @param string $start_date Start date from date filter.
		 * @param string $end_date End date from date filter.
		 * @param string $comparison_range_type '' | 'previous-period' | 'previous-year'.
		 *
		 * @return array
		 *
		 * @throws \InvalidArgumentException Throw Invalid start or end date from date filter.
		 */
		public function calculate_comparison_dates( $start_date, $end_date, $comparison_range_type = '' ) {

			// Explicitly normalize behavior.
			if ( 'previous-year' === $comparison_range_type ) {

				return array(
					'comparison_start_date' => gmdate( 'Y-m-d', strtotime( '-1 year' ) ),
					'comparison_end_date'   => gmdate( 'Y-m-d' ),
				);
			}

			// Default + 'previous-period'.
			$start_ts = strtotime( $start_date );
			$end_ts   = strtotime( $end_date );

			if ( false === $start_ts || false === $end_ts || $end_ts < $start_ts ) {
				throw new \InvalidArgumentException( 'Invalid start or end date.' );
			}

			// Duration in days (inclusive).
			$period_days = (int) floor( ( $end_ts - $start_ts ) / DAY_IN_SECONDS ) + 1;

			// Previous period ends one day before original start.
			$comparison_end_ts = strtotime( '-1 day', $start_ts );

			// Previous period starts with identical duration.
			$comparison_start_ts = strtotime(
				'-' . ( $period_days - 1 ) . ' days',
				$comparison_end_ts
			);

			return array(
				'comparison_start_date' => gmdate( 'Y-m-d', (int) $comparison_start_ts ),
				'comparison_end_date'   => gmdate( 'Y-m-d', (int) $comparison_end_ts ),
			);
		}
	}

	/**
	 * Prepare if class 'Cartflows_Pro_Analytics_Helper_Registry' exist.
	 * Kicking this off by calling 'get_instance()' method
	 */
	Cartflows_Pro_Analytics_Helper_Registry::get_instance();
}

if ( ! function_exists( 'wcf_pro_analytics' ) ) {

	/**
	 * Get analytics helper registry instance.
	 *
	 * Provides centralized access to all analytics functionality:
	 *
	 * Helper Classes:
	 * - wcf_pro_analytics()->database     - Database queries helper
	 * - wcf_pro_analytics()->calculations - Calculations helper
	 * - wcf_pro_analytics()->optin        - Optin analytics helper
	 * - wcf_pro_analytics()->products     - Products analytics helper
	 * - wcf_pro_analytics()->conversions  - Conversions analytics helper
	 * - wcf_pro_analytics()->funnels      - Funnels analytics helper
	 *
	 * Utility Methods (Direct Access):
	 * - wcf_pro_analytics()->format_price()
	 * - wcf_pro_analytics()->get_date_range()
	 * - wcf_pro_analytics()->format_date_time()
	 * - wcf_pro_analytics()->remove_zero_values()
	 * - ... and more utility methods
	 *
	 * @return Cartflows_Pro_Analytics_Helper_Registry Instance
	 */
	function wcf_pro_analytics() { //phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		return Cartflows_Pro_Analytics_Helper_Registry::get_instance();
	}
}

<?php
/**
 * Get current step data - factory.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Step factory
 *
 * @since 1.0.0
 */
class Cartflows_Pro_Step_Factory extends Cartflows_Step_Factory {

	/**
	 * Check for offer page
	 *
	 * @return bool
	 */
	public function is_offer_page() {

		$step_type = $this->get_step_type();

		if ( 'upsell' === $step_type || 'downsell' === $step_type ) {

			return true;
		}

		return false;
	}

	/**
	 * Get next step id according to condition.
	 *
	 * @since 1.6.13
	 *
	 * @return bool|int
	 */
	public function get_next_step_id() {

		$next_step_id = false;

		$flow_id = $this->get_flow_id();

		if ( $flow_id ) {

			$flow_steps   = $this->get_flow_steps();
			$control_step = $this->get_control_step();

			if ( is_array( $flow_steps ) ) {
				$flow_steps_count = count( $flow_steps );
				
				foreach ( $flow_steps as $index => $data ) {

					if ( intval( $data['id'] ) === $control_step ) {

						// Find the next enabled step.
						for ( $i = $index + 1; $i < $flow_steps_count; $i++ ) {

							if ( ! empty( $flow_steps[ $i ]['id'] ) && ! wcf_pro()->utils->is_step_disabled( $flow_steps[ $i ]['id'] ) ) {

								$next_step_id = intval( $flow_steps[ $i ]['id'] );
								break;
							}
						}

						break;
					}
				}
			}
		}

		return $next_step_id;
	}

	/**
	 * Get thank you page ID.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|int
	 */
	public function get_thankyou_page_id() {

		$steps               = $this->get_flow_steps();
		$thankyou_step_id    = false;
		$thankyou_step_index = false;

		if ( empty( $steps ) ) {
			return $thankyou_step_id;
		}

		foreach ( $steps as $i => $step ) {

			if ( 'thankyou' === $step['type'] ) {

				$thankyou_step_id = intval( $step['id'] );
				break;
			}
		}

		return $thankyou_step_id;
	}

	/**
	 * Get prev control id according to condition.
	 *
	 * @since 1.6.13
	 *
	 * @return bool|int
	 */
	public function get_prev_control_id() {

		$prev_step_id = false;

		$flow_id = $this->get_flow_id();

		if ( $flow_id ) {

			$flow_steps   = $this->get_flow_steps();
			$control_step = $this->get_control_step();

			if ( is_array( $flow_steps ) ) {

				foreach ( $flow_steps as $index => $data ) {

					if ( intval( $data['id'] ) === $control_step ) {

						$prev_step_index = $index - 1;

						if ( isset( $flow_steps[ $prev_step_index ] ) ) {

							$prev_step_id = intval( $flow_steps[ $prev_step_index ]['id'] );
						}

						break;
					}
				}
			}
		}

		return $prev_step_id;
	}

	/**
	 * Get prev control id according to condition.
	 *
	 * @param int   $current_flow flow id.
	 * @param array $flow_cookie_data cookie data.
	 * @since x.x.x
	 *
	 * @return bool|int
	 */
	public function get_prev_control_id_for_analytics( $current_flow, $flow_cookie_data ) {

		$prev_step_id = false;

		if ( $current_flow ) {

			$flow_steps   = $this->get_flow_steps();
			$control_step = $this->get_control_step();

			if ( is_array( $flow_steps ) && ! empty( $flow_cookie_data ) ) {

				foreach ( $flow_steps as $index => $data ) {

					if ( intval( $data['id'] ) === $control_step ) {
						$prev_step_id = $this->find_step_id_recursively( $index, $flow_steps, $flow_cookie_data );
					}
				}
			}
		}

		return $prev_step_id;
	}

	/**
	 * Get prev control id according to condition.
	 *
	 * @param int   $index flow id.
	 * @param array $flow_steps flow steps.
	 * @param array $flow_cookie_data cookie data.
	 * @since x.x.x
	 *   */
	public function find_step_id_recursively( $index, $flow_steps, $flow_cookie_data ) {

		$prev_step_index = $index - 1;

		$prev_step_id = false;

		if ( $prev_step_index < 0 ) {
			return $prev_step_id;
		}

		if ( isset( $flow_steps[ $prev_step_index ] ) && in_array( $flow_steps[ $prev_step_index ]['id'], $flow_cookie_data, true ) ) {
			return intval( $flow_steps[ $prev_step_index ]['id'] );
		} else {
			$prev_step_id = $this->find_step_id_recursively( $prev_step_index, $flow_steps, $flow_cookie_data );
		}

		return $prev_step_id;
	}

	/**
	 * Get next step if for the given step ID.
	 *
	 * @param int $current_step_id The current step ID.
	 * @param int $step_to_redirect The step to which needs to be redirected by default.
	 * @return int $step_to_redirect the step to which needs to be redirected after found the next step.
	 * @since x.x.x
	 */
	public function get_next_step_from_given_step( $current_step_id, $step_to_redirect ) {

		$flow_id = wcf()->utils->get_flow_id_from_step_id( $current_step_id );
		$steps   = get_post_meta( $flow_id, 'wcf-steps', true );

		if ( ! empty( $steps ) && is_array( $steps ) ) {

			$current_step_found = false;

			foreach ( $steps as $index => $step ) {

				if ( $current_step_found ) {

					if ( in_array( $step['type'], array( 'upsell', 'thankyou' ), true ) ) {

						$step_to_redirect = $step['id'];
						break;
					}
				} elseif ( intval( $step['id'] ) === $current_step_id ) {


						$current_step_found = true;
				}
			}
		}

		return $step_to_redirect;
	}
}

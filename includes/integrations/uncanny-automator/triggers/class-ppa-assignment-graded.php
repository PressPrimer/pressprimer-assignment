<?php
/**
 * Assignment Graded Trigger
 *
 * Fires when an assignment submission is graded (regardless of pass/fail).
 *
 * @package PressPrimer_Assignment
 * @subpackage Integrations\UncannyAutomator
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assignment Graded Trigger
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Assignment_Graded extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Helpers instance
	 *
	 * @var PressPrimer_Assignment_Automator_Helpers
	 */
	protected $helpers;

	/**
	 * Setup the trigger
	 *
	 * @since 1.0.0
	 */
	protected function setup_trigger() {
		// Get helpers from dependencies.
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'PPA' );
		$this->set_trigger_code( 'PPA_ASSIGNMENT_GRADED' );
		$this->set_trigger_meta( 'PPA_ASSIGNMENT' );
		$this->set_is_login_required( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Assignment title placeholder */
				esc_attr__( 'A user\'s {{assignment:%1$s}} is graded', 'pressprimer-assignment' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_attr__( 'A user\'s {{assignment}} is graded', 'pressprimer-assignment' )
		);

		// Hook: pressprimer_assignment_submission_graded( $submission_id, $score ).
		$this->add_action( 'pressprimer_assignment_submission_graded', 10, 2 );
	}

	/**
	 * Define trigger options (dropdown fields)
	 *
	 * @since 1.0.0
	 *
	 * @return array Options array.
	 */
	public function options() {
		$assignment_options = array(
			array(
				'text'  => esc_attr__( 'Any assignment', 'pressprimer-assignment' ),
				'value' => '-1',
			),
		);

		$assignment_options = array_merge( $assignment_options, $this->helpers->get_assignment_options() );

		return array(
			array(
				'input_type'  => 'select',
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_attr__( 'Assignment', 'pressprimer-assignment' ),
				'required'    => true,
				'options'     => $assignment_options,
			),
		);
	}

	/**
	 * Validate the trigger
	 *
	 * @since 1.0.0
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 * @return bool True if trigger should fire.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected = $trigger['meta'][ $this->get_trigger_meta() ];

		// Load submission and assignment from ID.
		list( $submission_id )           = $hook_args;
		list( $submission, $assignment ) = $this->helpers->load_objects_from_submission_id( $submission_id );

		if ( ! $submission || ! $assignment ) {
			return false;
		}

		// Any assignment.
		if ( '-1' === $selected || -1 === (int) $selected ) {
			return true;
		}

		// Specific assignment.
		return (int) $selected === (int) $assignment->id;
	}

	/**
	 * Define tokens for this trigger
	 *
	 * @since 1.0.0
	 *
	 * @param array $trigger Trigger data.
	 * @param array $tokens  Existing tokens.
	 * @return array Modified tokens.
	 */
	public function define_tokens( $trigger, $tokens ) {
		return $this->helpers->get_token_definitions( $tokens );
	}

	/**
	 * Hydrate tokens with actual values
	 *
	 * @since 1.0.0
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 * @return array Token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $submission_id )           = $hook_args;
		list( $submission, $assignment ) = $this->helpers->load_objects_from_submission_id( $submission_id );

		return $this->helpers->get_token_data_from_objects( $submission, $assignment );
	}
}

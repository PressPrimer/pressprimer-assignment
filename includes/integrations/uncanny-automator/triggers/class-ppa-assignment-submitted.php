<?php
/**
 * Assignment Submitted Trigger
 *
 * Fires when a user submits an assignment.
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
 * Assignment Submitted Trigger
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Assignment_Submitted extends \Uncanny_Automator\Recipe\Trigger {

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
		$this->set_trigger_code( 'PPA_ASSIGNMENT_SUBMITTED' );
		$this->set_trigger_meta( 'PPA_ASSIGNMENT' );
		$this->set_is_login_required( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Assignment title placeholder */
				esc_attr__( 'A user submits {{an assignment:%1$s}}', 'pressprimer-assignment' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_attr__( 'A user submits {{an assignment}}', 'pressprimer-assignment' )
		);

		// Hook: pressprimer_assignment_submission_submitted( $submission, $assignment ).
		$this->add_action( 'pressprimer_assignment_submission_submitted', 10, 2 );
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

		// Get submission and assignment from hook args.
		list( $submission, $assignment ) = $hook_args;

		$assignment_id = $assignment->id ?? 0;

		// Any assignment.
		if ( '-1' === $selected || -1 === (int) $selected ) {
			return true;
		}

		// Specific assignment.
		return (int) $selected === (int) $assignment_id;
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
		return $this->helpers->get_submission_token_definitions( $tokens );
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
		list( $submission, $assignment ) = $hook_args;

		return $this->helpers->get_token_data_from_objects( $submission, $assignment );
	}
}

/**
 * Scheduling Panel Component
 *
 * The assignment editor's Scheduling tab: the assignment-wide due date
 * and late policy, followed by any addon scheduling sections. The
 * Educator addon renders its per-student overrides panel here, so the
 * whole deadline story reads top to bottom — assignment default, then
 * individual exceptions.
 *
 * @package
 * @since 2.2.0
 */

import UpsellPrompt from '../../shared/components/UpsellPrompt';
import LatePolicyCard from './LatePolicyCard';

// Premium touchpoints for this surface, resolved server-side by the
// touchpoint registry (empty for non-admins or when the addons are active).
const editorTouchpoints = window.pressprimerAssignmentAdmin?.touchpoints || {};

/**
 * Scheduling Panel
 *
 * @param {Object} props      Component props.
 * @param {Object} props.form Ant Design form instance.
 * @return {JSX.Element} The scheduling panel.
 */
const SchedulingPanel = ( { form } ) => {
	return (
		<div className="ppa-editor-scheduling-panel">
			{ /* Due Date & Late Policy (2.2) */ }
			<LatePolicyCard form={ form } />

			{ /* Per-student overrides touchpoint — where the Educator
			    overrides panel lives once active. Server-gated. */ }
			{ editorTouchpoints[ 'scheduling-tab' ] && (
				<UpsellPrompt
					touchpoint={ editorTouchpoints[ 'scheduling-tab' ] }
				/>
			) }

			{ /* Addon scheduling panels — registered via the
			    window.PPAEditorSchedulingAddons array so addons can
			    inject their own scheduling sections without coupling.
			    Each entry is a React component rendered with no
			    props; the addon reads context (e.g., assignmentId)
			    from window.pressprimerAssignmentEditorData. */ }
			{ ( typeof window !== 'undefined' &&
			Array.isArray( window.PPAEditorSchedulingAddons )
				? window.PPAEditorSchedulingAddons
				: []
			).map( ( AddonPanel, index ) => (
				<AddonPanel key={ `ppa-addon-scheduling-${ index }` } />
			) ) }
		</div>
	);
};

export default SchedulingPanel;

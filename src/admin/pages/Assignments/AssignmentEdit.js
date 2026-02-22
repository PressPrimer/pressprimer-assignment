/**
 * Assignment Edit Component
 *
 * Loads an existing assignment and renders the AssignmentForm
 * in edit mode.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spin, Alert } from 'antd';
import { useParams } from 'react-router-dom';
import { assignmentsApi } from '../../api';
import AssignmentForm from './AssignmentForm';

/**
 * AssignmentEdit component.
 *
 * Fetches assignment data by ID and passes it to AssignmentForm.
 *
 * @return {JSX.Element} Edit assignment view.
 */
export default function AssignmentEdit() {
	const { id } = useParams();
	const [ assignment, setAssignment ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const fetchAssignment = async () => {
			try {
				setLoading( true );
				setError( null );
				const data = await assignmentsApi.get( id );
				setAssignment( data );
			} catch ( err ) {
				setError(
					err.message ||
						__(
							'Failed to load assignment.',
							'pressprimer-assignment'
						)
				);
			} finally {
				setLoading( false );
			}
		};

		if ( id ) {
			fetchAssignment();
		}
	}, [ id ] );

	if ( loading ) {
		return (
			<div style={ { textAlign: 'center', padding: 50 } }>
				<Spin size="large" />
			</div>
		);
	}

	if ( error ) {
		return (
			<Alert
				type="error"
				message={ __(
					'Error Loading Assignment',
					'pressprimer-assignment'
				) }
				description={ error }
				showIcon
			/>
		);
	}

	return <AssignmentForm initialData={ assignment } isEdit />;
}

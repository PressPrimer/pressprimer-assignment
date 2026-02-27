/**
 * Dashboard - Main Component
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spin, Alert } from 'antd';

import StatsCards from './StatsCards';
import ActivityChart from './ActivityChart';
import PopularAssignments from './PopularAssignments';
import QuickActions from './QuickActions';
import RecentActivity from './RecentActivity';

/**
 * Dashboard Component
 *
 * @param {Object} props             Component props.
 * @param {Object} props.initialData Initial dashboard data from PHP.
 * @return {JSX.Element} Rendered component.
 */
const Dashboard = ( { initialData = {} } ) => {
	const [ stats, setStats ] = useState( null );
	const [ recentSubmissions, setRecentSubmissions ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	// Fetch dashboard data.
	useEffect( () => {
		const fetchData = async () => {
			try {
				setLoading( true );

				// Fetch stats and recent submissions in parallel.
				const [ statsResponse, submissionsResponse ] =
					await Promise.all( [
						apiFetch( {
							path: '/ppa/v1/statistics/dashboard',
						} ),
						apiFetch( {
							path: '/ppa/v1/statistics/submissions?per_page=10',
						} ),
					] );

				if ( statsResponse.success ) {
					setStats( statsResponse.data );
				}

				if ( submissionsResponse.success ) {
					setRecentSubmissions(
						submissionsResponse.data.items || []
					);
				}

				setError( null );
			} catch ( err ) {
				setError(
					err.message ||
						__(
							'Failed to load dashboard data.',
							'pressprimer-assignment'
						)
				);
			} finally {
				setLoading( false );
			}
		};

		fetchData();
	}, [] );

	/**
	 * Launch the onboarding tour.
	 */
	const handleLaunchTour = useCallback( async () => {
		// Reset onboarding state via AJAX to start from step 1.
		const onboardingData = window.pressprimerAssignmentOnboardingData || {};

		try {
			const formData = new FormData();
			formData.append(
				'action',
				'pressprimer_assignment_onboarding_progress'
			);
			formData.append( 'onboarding_action', 'reset' );
			formData.append( 'nonce', onboardingData.nonce || '' );

			await fetch( onboardingData.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} );
		} catch {
			// Silently fail - reset is best-effort.
		}

		// Launch the onboarding wizard.
		if ( typeof window.ppaLaunchOnboarding === 'function' ) {
			window.ppaLaunchOnboarding();
		}
	}, [] );

	// Get branding data from localized PHP data.
	const dashboardData = window.pressprimerAssignmentDashboardData || {};
	const pluginUrl = dashboardData.pluginUrl || '';
	const pluginName = dashboardData.pluginName || 'PressPrimer Assignment';
	const dashboardLogo =
		dashboardData.dashboardLogo ||
		`${ pluginUrl }assets/images/PressPrimer-Logo-White.svg`;
	const welcomeText =
		dashboardData.welcomeText ||
		__(
			"Welcome to PressPrimer Assignment! Here's an overview of recent assignment activity.",
			'pressprimer-assignment'
		);

	return (
		<div className="ppa-dashboard-container">
			{ /* Header */ }
			<div className="ppa-dashboard-header">
				<div className="ppa-dashboard-header-content">
					<img
						src={ dashboardLogo }
						alt={ pluginName }
						className="ppa-dashboard-logo"
					/>
					<p>{ welcomeText }</p>
				</div>
			</div>

			{ /* Main Content */ }
			{ error && (
				<Alert
					message={ __( 'Error', 'pressprimer-assignment' ) }
					description={ error }
					type="error"
					showIcon
					style={ { marginBottom: 24 } }
				/>
			) }

			<Spin
				spinning={ loading }
				tip={ __( 'Loading…', 'pressprimer-assignment' ) }
			>
				<div className="ppa-dashboard-content">
					{ /* Top Row: Stats Cards (2fr) + Quick Actions (1fr) */ }
					<div className="ppa-dashboard-top-row">
						<StatsCards stats={ stats } loading={ loading } />
						<QuickActions
							urls={ initialData.urls || {} }
							onLaunchTour={ handleLaunchTour }
						/>
					</div>

					{ /* Activity Chart */ }
					<ActivityChart loading={ loading } />

					{ /* Bottom Grid */ }
					<div className="ppa-dashboard-grid">
						{ /* Left Column */ }
						<div className="ppa-dashboard-main">
							<RecentActivity
								submissions={ recentSubmissions }
								loading={ loading }
							/>
						</div>

						{ /* Right Column */ }
						<div className="ppa-dashboard-sidebar">
							<PopularAssignments
								assignments={ stats?.popular_assignments || [] }
								loading={ loading }
							/>
						</div>
					</div>
				</div>
			</Spin>
		</div>
	);
};

export default Dashboard;

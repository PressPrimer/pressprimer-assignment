/**
 * Admin App Component
 *
 * Root component with Ant Design configuration and hash-based routing.
 *
 * @package
 * @since 1.0.0
 */

import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ConfigProvider } from 'antd';
import { __ } from '@wordpress/i18n';
import Assignments from './pages/Assignments';
import AssignmentForm from './pages/Assignments/AssignmentForm';
import AssignmentEdit from './pages/Assignments/AssignmentEdit';

/**
 * Placeholder component for Submissions page.
 */
function Submissions() {
	return (
		<div>
			<h2>{ __( 'Submissions', 'pressprimer-assignment' ) }</h2>
			<p>
				{ __(
					'Submission review and grading interface will be built here.',
					'pressprimer-assignment'
				) }
			</p>
		</div>
	);
}

/**
 * Placeholder component for Categories page.
 */
function Categories() {
	return (
		<div>
			<h2>{ __( 'Categories', 'pressprimer-assignment' ) }</h2>
			<p>
				{ __(
					'Category and tag management interface will be built here.',
					'pressprimer-assignment'
				) }
			</p>
		</div>
	);
}

/**
 * Placeholder component for Reports page.
 */
function Reports() {
	return (
		<div>
			<h2>{ __( 'Reports', 'pressprimer-assignment' ) }</h2>
			<p>
				{ __(
					'Analytics and reporting interface will be built here.',
					'pressprimer-assignment'
				) }
			</p>
		</div>
	);
}

/**
 * Placeholder component for Settings page.
 */
function Settings() {
	return (
		<div>
			<h2>{ __( 'Settings', 'pressprimer-assignment' ) }</h2>
			<p>
				{ __(
					'Plugin settings interface will be built here.',
					'pressprimer-assignment'
				) }
			</p>
		</div>
	);
}

/**
 * Main App component.
 *
 * Wraps the application with Ant Design ConfigProvider and React Router.
 */
export default function App() {
	return (
		<ConfigProvider
			theme={ {
				token: {
					colorPrimary: '#1677ff',
				},
			} }
		>
			<HashRouter>
				<div className="ppa-admin-app">
					<Routes>
						<Route
							path="/assignments"
							element={ <Assignments /> }
						/>
						<Route
							path="/assignments/new"
							element={ <AssignmentForm /> }
						/>
						<Route
							path="/assignments/:id/edit"
							element={ <AssignmentEdit /> }
						/>
						<Route
							path="/submissions"
							element={ <Submissions /> }
						/>
						<Route path="/categories" element={ <Categories /> } />
						<Route path="/reports" element={ <Reports /> } />
						<Route path="/settings" element={ <Settings /> } />
						<Route
							path="*"
							element={ <Navigate to="/assignments" replace /> }
						/>
					</Routes>
				</div>
			</HashRouter>
		</ConfigProvider>
	);
}

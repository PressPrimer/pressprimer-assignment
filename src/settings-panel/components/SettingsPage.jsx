/**
 * Settings Page - Main Component
 *
 * Vertical tabs layout matching PressPrimer Quiz settings page.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, message, Spin } from 'antd';
import {
	SettingOutlined,
	MailOutlined,
	ToolOutlined,
	SaveOutlined,
	ApiOutlined,
	BgColorsOutlined,
	InfoCircleOutlined,
} from '@ant-design/icons';

import GeneralTab from './GeneralTab';
import AppearanceTab from './AppearanceTab';
import EmailTab from './EmailTab';
import IntegrationsTab from './IntegrationsTab';
import AdvancedTab from './AdvancedTab';
import StatusTab from './StatusTab';

/**
 * Core tab configuration (built into free plugin).
 * Order values match the server-side settingsTabs filter.
 */
const CORE_TABS = [
	{
		id: 'general',
		label: __( 'General', 'pressprimer-assignment' ),
		icon: <SettingOutlined />,
		component: GeneralTab,
		order: 10,
	},
	{
		id: 'appearance',
		label: __( 'Appearance', 'pressprimer-assignment' ),
		icon: <BgColorsOutlined />,
		component: AppearanceTab,
		order: 20,
	},
	{
		id: 'email',
		label: __( 'Email', 'pressprimer-assignment' ),
		icon: <MailOutlined />,
		component: EmailTab,
		order: 30,
	},
	{
		id: 'integration',
		label: __( 'Integrations', 'pressprimer-assignment' ),
		icon: <ApiOutlined />,
		component: IntegrationsTab,
		order: 50,
	},
	{
		id: 'status',
		label: __( 'Status', 'pressprimer-assignment' ),
		icon: <InfoCircleOutlined />,
		component: StatusTab,
		order: 100,
	},
	{
		id: 'advanced',
		label: __( 'Advanced', 'pressprimer-assignment' ),
		icon: <ToolOutlined />,
		component: AdvancedTab,
		order: 110,
	},
];

/**
 * Read-only tabs that don't show the Save button.
 * These tabs display information only and have no editable settings.
 */
const READ_ONLY_TABS = [ 'status' ];

/**
 * Settings Page Component
 *
 * @param {Object} props              Component props.
 * @param {Object} props.settingsData Initial settings data from PHP.
 */
const SettingsPage = ( { settingsData = {} } ) => {
	// Get initial tab from URL query parameter, default to 'general'.
	const getInitialTab = () => {
		const params = new URLSearchParams( window.location.search );
		return params.get( 'tab' ) || 'general';
	};

	const [ activeTab, setActiveTab ] = useState( getInitialTab );
	const [ settings, setSettings ] = useState( settingsData.settings || {} );
	const [ saving, setSaving ] = useState( false );
	const [ hasChanges, setHasChanges ] = useState( false );

	/**
	 * Build combined tabs from core tabs and addon tabs from settingsTabs.
	 * Addon plugins can register additional tabs via PHP filter.
	 */
	const allTabs = useMemo( () => {
		const serverTabs = settingsData.settingsTabs || {};
		const combined = [];

		// Add core tabs with their components.
		CORE_TABS.forEach( ( coreTab ) => {
			combined.push( {
				...coreTab,
				isAddon: false,
			} );
		} );

		// Add addon tabs - only those explicitly marked with isAddon: true.
		const coreIds = CORE_TABS.map( ( t ) => t.id );
		Object.entries( serverTabs ).forEach( ( [ id, tabConfig ] ) => {
			if ( coreIds.includes( id ) ) {
				return;
			}

			if ( tabConfig.isAddon === true ) {
				combined.push( {
					id,
					label: tabConfig.label || id,
					icon: <SettingOutlined />,
					component: null,
					order: tabConfig.order ?? 50,
					isAddon: true,
				} );
			}
		} );

		// Sort by order.
		combined.sort( ( a, b ) => a.order - b.order );

		return combined;
	}, [ settingsData.settingsTabs ] );

	/**
	 * Check if active tab is an addon tab or read-only tab.
	 */
	const activeTabConfig = allTabs.find( ( tab ) => tab.id === activeTab );
	const isAddonTab = activeTabConfig?.isAddon ?? false;
	const isReadOnly = READ_ONLY_TABS.includes( activeTab );

	/**
	 * Update a setting value.
	 */
	const updateSetting = useCallback( ( key, value ) => {
		setSettings( ( prev ) => ( {
			...prev,
			[ key ]: value,
		} ) );
		setHasChanges( true );
	}, [] );

	/**
	 * Save all settings.
	 */
	const handleSave = async () => {
		try {
			setSaving( true );

			const response = await apiFetch( {
				path: '/ppa/v1/settings',
				method: 'POST',
				data: settings,
			} );

			if ( response.success ) {
				message.success(
					__(
						'Settings saved successfully!',
						'pressprimer-assignment'
					)
				);
				setHasChanges( false );
			} else {
				message.error(
					response.message ||
						__(
							'Failed to save settings.',
							'pressprimer-assignment'
						)
				);
			}
		} catch ( error ) {
			message.error(
				error.message ||
					__( 'Failed to save settings.', 'pressprimer-assignment' )
			);
		} finally {
			setSaving( false );
		}
	};

	/**
	 * Get the active tab component (for core tabs).
	 */
	const ActiveTabComponent = activeTabConfig?.component || null;

	// Get the mascot image from localized data.
	const pluginUrl = settingsData.pluginUrl || '';
	const settingsMascot =
		settingsData.settingsMascot ||
		`${ pluginUrl }assets/images/construction-mascot.png`;

	return (
		<div className="ppa-settings-container">
			{ /* Header */ }
			<div className="ppa-settings-header">
				<div className="ppa-settings-header-content">
					<h1>
						{ __(
							'Assignment Settings',
							'pressprimer-assignment'
						) }
					</h1>
					<p>
						{ __(
							'Configure your assignment plugin settings, email notifications, and preferences.',
							'pressprimer-assignment'
						) }
					</p>
				</div>
				<img
					src={ settingsMascot }
					alt=""
					className="ppa-settings-header-mascot"
				/>
			</div>

			{ /* Main Layout */ }
			<div className="ppa-settings-layout">
				{ /* Vertical Tab Navigation */ }
				<nav className="ppa-settings-tabs">
					{ allTabs.map( ( tab ) => (
						<button
							key={ tab.id }
							type="button"
							className={ `ppa-settings-tab ${
								activeTab === tab.id
									? 'ppa-settings-tab--active'
									: ''
							}` }
							onClick={ () => setActiveTab( tab.id ) }
						>
							{ tab.icon }
							<span>{ tab.label }</span>
						</button>
					) ) }
				</nav>

				{ /* Tab Content */ }
				<div className="ppa-settings-content">
					{ /* Read-only tab content (no save button) */ }
					{ ! isAddonTab && isReadOnly && ActiveTabComponent && (
						<ActiveTabComponent
							settings={ settings }
							updateSetting={ updateSetting }
							settingsData={ settingsData }
						/>
					) }

					{ /* Editable tab content */ }
					{ ! isAddonTab && ! isReadOnly && ActiveTabComponent && (
						<Spin
							spinning={ saving }
							tip={ __(
								'Saving\u2026',
								'pressprimer-assignment'
							) }
						>
							<ActiveTabComponent
								settings={ settings }
								updateSetting={ updateSetting }
								settingsData={ settingsData }
							/>

							{ /* Save Button Footer */ }
							<div className="ppa-settings-footer">
								<Button
									type="primary"
									size="large"
									icon={ <SaveOutlined /> }
									onClick={ handleSave }
									loading={ saving }
									disabled={ ! hasChanges }
								>
									{ __(
										'Save Settings',
										'pressprimer-assignment'
									) }
								</Button>
							</div>
						</Spin>
					) }

					{ /* Addon tab mount points */ }
					{ allTabs
						.filter( ( t ) => t.isAddon )
						.map( ( tab ) => (
							<div
								key={ tab.id }
								id={ `ppa-settings-addon-${ tab.id }` }
								className="ppa-settings-addon-content"
								style={ {
									display:
										activeTab === tab.id ? 'block' : 'none',
								} }
							/>
						) ) }
				</div>
			</div>
		</div>
	);
};

export default SettingsPage;

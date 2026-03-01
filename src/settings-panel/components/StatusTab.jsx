/**
 * Status Tab Component
 *
 * System information, diagnostics, and database status.
 * Ported from PressPrimer Quiz StatusTab with modifications
 * for Assignment-specific data.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Typography, Tag, Space, Button, Alert, message } from 'antd';
import {
	CheckCircleOutlined,
	CloseCircleOutlined,
	ExclamationCircleOutlined,
	ToolOutlined,
	CopyOutlined,
} from '@ant-design/icons';

const { Title, Paragraph } = Typography;

/**
 * LMS display names (Assignment supports these in 1.0)
 */
const LMS_NAMES = {
	learndash: 'LearnDash',
	tutorlms: 'Tutor LMS',
};

/**
 * Status Tab - System information and diagnostics
 *
 * @param {Object} props              Component props.
 * @param {Object} props.settingsData Full settings data including system info.
 */
const StatusTab = ( { settingsData } ) => {
	const systemInfo = useMemo(
		() => settingsData.systemInfo || {},
		[ settingsData.systemInfo ]
	);
	const lmsStatus = useMemo(
		() => settingsData.lmsStatus || {},
		[ settingsData.lmsStatus ]
	);
	const initialTables = settingsData.databaseTables || [];
	const nonces = settingsData.nonces || {};

	const [ databaseTables, setDatabaseTables ] = useState( initialTables );
	const [ isRepairing, setIsRepairing ] = useState( false );

	/**
	 * Check if any tables are missing
	 */
	const hasMissingTables = databaseTables.some( ( table ) => ! table.exists );

	/**
	 * Handle repair database tables
	 */
	const handleRepairTables = async () => {
		setIsRepairing( true );

		try {
			const formData = new FormData();
			formData.append(
				'action',
				'pressprimer_assignment_repair_database_tables'
			);
			formData.append( 'nonce', nonces.repairTables );

			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				body: formData,
			} );

			const result = await response.json();

			if ( result.success ) {
				message.success( result.data.message );
				if ( result.data.tableStatus ) {
					setDatabaseTables( result.data.tableStatus );
				}
			} else {
				message.error(
					result.data?.message ||
						__(
							'Failed to repair tables.',
							'pressprimer-assignment'
						)
				);
			}
		} catch ( error ) {
			message.error(
				__(
					'An error occurred. Please try again.',
					'pressprimer-assignment'
				)
			);
		} finally {
			setIsRepairing( false );
		}
	};

	/**
	 * Get short table name (remove prefix)
	 *
	 * @param {string} fullName Full table name with prefix.
	 */
	const getShortTableName = ( fullName ) => {
		const match = fullName.match( /ppa_(.+)$/ );
		return match ? match[ 1 ] : fullName;
	};

	/**
	 * Format version with requirement check
	 *
	 * @param {string} current  Current version string.
	 * @param {string} required Minimum required version string.
	 */
	const formatVersionWithCheck = ( current, required ) => {
		const meetsRequirement = compareVersions( current, required ) >= 0;
		return (
			<Space>
				<span>{ current }</span>
				{ meetsRequirement ? (
					<Tag icon={ <CheckCircleOutlined /> } color="success">
						{ __( 'OK', 'pressprimer-assignment' ) }
					</Tag>
				) : (
					<Tag icon={ <CloseCircleOutlined /> } color="error">
						{ sprintf(
							/* translators: %s: minimum required version number */
							__( 'Requires %s+', 'pressprimer-assignment' ),
							required
						) }
					</Tag>
				) }
			</Space>
		);
	};

	/**
	 * Simple version comparison
	 *
	 * @param {string} a First version string.
	 * @param {string} b Second version string.
	 */
	const compareVersions = ( a, b ) => {
		if ( ! a || ! b ) {
			return 0;
		}
		const aParts = String( a ).split( '.' ).map( Number );
		const bParts = String( b ).split( '.' ).map( Number );

		for ( let i = 0; i < Math.max( aParts.length, bParts.length ); i++ ) {
			const aPart = aParts[ i ] || 0;
			const bPart = bParts[ i ] || 0;
			if ( aPart > bPart ) {
				return 1;
			}
			if ( aPart < bPart ) {
				return -1;
			}
		}
		return 0;
	};

	/**
	 * Build plain-text diagnostic string for clipboard
	 */
	const buildDiagnosticText = useCallback( () => {
		const lines = [];
		const sep = '---';

		lines.push( '### PressPrimer Assignment - System Status ###' );
		lines.push( '' );

		// Plugin info.
		lines.push( '## Plugin' );
		lines.push(
			`Plugin Version: ${ systemInfo.pluginVersion || 'Unknown' }`
		);
		lines.push(
			`Database Version: ${ systemInfo.dbVersion || 'Not set' }`
		);
		lines.push( '' );

		// WordPress environment.
		lines.push( '## WordPress' );
		lines.push( `Site URL: ${ systemInfo.siteUrl || 'Unknown' }` );
		lines.push(
			`WordPress Version: ${ systemInfo.wpVersion || 'Unknown' }`
		);
		lines.push( `Multisite: ${ systemInfo.isMultisite ? 'Yes' : 'No' }` );
		lines.push( `Memory Limit: ${ systemInfo.memoryLimit || 'Unknown' }` );
		lines.push( `Active Theme: ${ systemInfo.activeTheme || 'Unknown' }` );
		lines.push( '' );

		// Server environment.
		lines.push( '## Server' );
		lines.push( `PHP Version: ${ systemInfo.phpVersion || 'Unknown' }` );
		lines.push(
			`PHP Post Max Size: ${ systemInfo.postMaxSize || 'Unknown' }`
		);
		lines.push(
			`Upload Max Filesize: ${
				systemInfo.uploadMaxFilesize || 'Unknown'
			}`
		);
		lines.push(
			`PHP Time Limit: ${
				systemInfo.maxExecutionTime || 'Unknown'
			} seconds`
		);
		lines.push(
			`MySQL Version: ${ systemInfo.mysqlVersion || 'Unknown' }`
		);
		lines.push( '' );

		// LMS integrations.
		const activeLms = Object.entries( lmsStatus )
			.filter( ( [ , info ] ) => info.active )
			.map(
				( [ key, info ] ) =>
					`${ LMS_NAMES[ key ] || key } ${ info.version || '' }`
			);

		if ( activeLms.length > 0 ) {
			lines.push( '## LMS Integrations' );
			activeLms.forEach( ( lms ) => lines.push( lms ) );
			lines.push( '' );
		}

		// File handling capabilities.
		const caps = systemInfo.fileHandlingCapabilities || {};
		lines.push( '## File Handling' );
		lines.push(
			`MIME Detection (finfo): ${
				caps.finfo ? 'Available' : 'Not Available'
			}`
		);
		lines.push(
			`PDF Parser Library: ${
				caps.pdfParserLibrary ? 'Available' : 'Not Available'
			}`
		);
		lines.push(
			`pdftotext: ${ caps.pdftotext ? 'Available' : 'Not Available' }`
		);
		lines.push(
			`DOCX Support (ZipArchive): ${
				caps.zipArchive ? 'Available' : 'Not Available'
			}`
		);
		lines.push(
			`Image Processing (GD): ${
				caps.gd ? 'Available' : 'Not Available'
			}`
		);
		lines.push( '' );

		// Statistics.
		lines.push( '## Statistics' );
		lines.push( `Assignments: ${ systemInfo.totalAssignments ?? 0 }` );
		lines.push( `Submissions: ${ systemInfo.totalSubmissions ?? 0 }` );
		lines.push( `Files: ${ systemInfo.totalFiles ?? 0 }` );
		lines.push( `Categories: ${ systemInfo.totalCategories ?? 0 }` );
		lines.push( '' );

		// Database tables.
		lines.push( '## Database Tables' );
		databaseTables.forEach( ( table ) => {
			const status = table.exists ? 'OK' : 'MISSING';
			const rows = table.exists ? ` (${ table.row_count } rows)` : '';
			lines.push(
				`${ getShortTableName( table.name ) }: ${ status }${ rows }`
			);
		} );
		lines.push( '' );

		// Active plugins.
		const plugins = systemInfo.activePlugins || [];
		if ( plugins.length > 0 ) {
			lines.push( '## Active Plugins' );
			plugins.forEach( ( plugin ) => lines.push( plugin ) );
			lines.push( '' );
		}

		lines.push( sep );
		return lines.join( '\n' );
	}, [ systemInfo, lmsStatus, databaseTables ] );

	/**
	 * Copy diagnostic text to clipboard
	 */
	const handleCopyDiagnostics = useCallback( async () => {
		try {
			const text = buildDiagnosticText();
			await window.navigator.clipboard.writeText( text );
			message.success(
				__(
					'System status copied to clipboard.',
					'pressprimer-assignment'
				)
			);
		} catch ( clipboardError ) {
			// Fallback for older browsers / non-HTTPS.
			try {
				const textArea = document.createElement( 'textarea' );
				textArea.value = buildDiagnosticText();
				textArea.style.position = 'fixed';
				textArea.style.left = '-9999px';
				document.body.appendChild( textArea );
				textArea.select();
				document.execCommand( 'copy' );
				document.body.removeChild( textArea );
				message.success(
					__(
						'System status copied to clipboard.',
						'pressprimer-assignment'
					)
				);
			} catch ( fallbackError ) {
				message.error(
					__(
						'Failed to copy. Please try again.',
						'pressprimer-assignment'
					)
				);
			}
		}
	}, [ buildDiagnosticText ] );

	/**
	 * Render a capability status tag
	 *
	 * @param {boolean} available Whether the capability is available.
	 */
	const renderCapabilityTag = ( available ) => {
		return available ? (
			<Tag icon={ <CheckCircleOutlined /> } color="success">
				{ __( 'Available', 'pressprimer-assignment' ) }
			</Tag>
		) : (
			<Tag icon={ <CloseCircleOutlined /> } color="error">
				{ __( 'Not Available', 'pressprimer-assignment' ) }
			</Tag>
		);
	};

	const caps = systemInfo.fileHandlingCapabilities || {};

	return (
		<div>
			{ /* Copy to clipboard bar */ }
			<div className="ppa-status-copy-bar">
				<span className="ppa-status-copy-bar-text">
					{ __(
						'Copy all diagnostic information to share with support.',
						'pressprimer-assignment'
					) }
				</span>
				<Button
					icon={ <CopyOutlined /> }
					onClick={ handleCopyDiagnostics }
				>
					{ __( 'Copy System Status', 'pressprimer-assignment' ) }
				</Button>
			</div>

			{ /* Two-column grid for info sections */ }
			<div className="ppa-status-grid">
				{ /* Plugin Information */ }
				<div className="ppa-settings-section">
					<Title level={ 4 } className="ppa-settings-section-title">
						{ __( 'Plugin', 'pressprimer-assignment' ) }
					</Title>

					<table className="ppa-system-info">
						<tbody>
							<tr>
								<th>
									{ __(
										'Version',
										'pressprimer-assignment'
									) }
								</th>
								<td>{ systemInfo.pluginVersion || '1.0.0' }</td>
							</tr>
							<tr>
								<th>
									{ __(
										'DB Version',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ systemInfo.dbVersion ||
										__(
											'Not set',
											'pressprimer-assignment'
										) }
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				{ /* WordPress Environment */ }
				<div className="ppa-settings-section">
					<Title level={ 4 } className="ppa-settings-section-title">
						{ __( 'WordPress', 'pressprimer-assignment' ) }
					</Title>

					<table className="ppa-system-info">
						<tbody>
							<tr>
								<th>
									{ __(
										'Site URL',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									<code>
										{ systemInfo.siteUrl || 'Unknown' }
									</code>
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Version',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ formatVersionWithCheck(
										systemInfo.wpVersion,
										'6.0'
									) }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Multisite',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ systemInfo.isMultisite ? (
										<Tag color="blue">
											{ __(
												'Yes',
												'pressprimer-assignment'
											) }
										</Tag>
									) : (
										<Tag>
											{ __(
												'No',
												'pressprimer-assignment'
											) }
										</Tag>
									) }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Memory Limit',
										'pressprimer-assignment'
									) }
								</th>
								<td>{ systemInfo.memoryLimit || 'Unknown' }</td>
							</tr>
							<tr>
								<th>
									{ __( 'Theme', 'pressprimer-assignment' ) }
								</th>
								<td>{ systemInfo.activeTheme || 'Unknown' }</td>
							</tr>
						</tbody>
					</table>
				</div>

				{ /* Server Environment */ }
				<div className="ppa-settings-section">
					<Title level={ 4 } className="ppa-settings-section-title">
						{ __( 'Server', 'pressprimer-assignment' ) }
					</Title>

					<table className="ppa-system-info">
						<tbody>
							<tr>
								<th>
									{ __(
										'PHP Version',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ formatVersionWithCheck(
										systemInfo.phpVersion,
										'7.4'
									) }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Post Max Size',
										'pressprimer-assignment'
									) }
								</th>
								<td>{ systemInfo.postMaxSize || 'Unknown' }</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Upload Max Filesize',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ systemInfo.uploadMaxFilesize ||
										'Unknown' }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Time Limit',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ systemInfo.maxExecutionTime || 'Unknown' }{ ' ' }
									{ systemInfo.maxExecutionTime &&
										__(
											'seconds',
											'pressprimer-assignment'
										) }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'MySQL Version',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ systemInfo.mysqlVersion || 'Unknown' }
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				{ /* Statistics */ }
				<div className="ppa-settings-section">
					<Title level={ 4 } className="ppa-settings-section-title">
						{ __( 'Statistics', 'pressprimer-assignment' ) }
					</Title>

					<table className="ppa-system-info">
						<tbody>
							<tr>
								<th>
									{ __(
										'Assignments',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ (
										systemInfo.totalAssignments ?? 0
									).toLocaleString() }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Submissions',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ (
										systemInfo.totalSubmissions ?? 0
									).toLocaleString() }
								</td>
							</tr>
							<tr>
								<th>
									{ __( 'Files', 'pressprimer-assignment' ) }
								</th>
								<td>
									{ (
										systemInfo.totalFiles ?? 0
									).toLocaleString() }
								</td>
							</tr>
							<tr>
								<th>
									{ __(
										'Categories',
										'pressprimer-assignment'
									) }
								</th>
								<td>
									{ (
										systemInfo.totalCategories ?? 0
									).toLocaleString() }
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			{ /* Full-width sections below the grid */ }

			{ /* LMS Integrations */ }
			{ Object.keys( lmsStatus ).length > 0 && (
				<div className="ppa-settings-section">
					<Title level={ 4 } className="ppa-settings-section-title">
						{ __( 'LMS Integrations', 'pressprimer-assignment' ) }
					</Title>

					<table className="ppa-system-info">
						<tbody>
							{ Object.entries( lmsStatus ).map(
								( [ key, info ] ) => (
									<tr key={ key }>
										<th>{ LMS_NAMES[ key ] || key }</th>
										<td>
											{ info.active ? (
												<Space>
													<Tag
														icon={
															<CheckCircleOutlined />
														}
														color="success"
													>
														{ __(
															'Active',
															'pressprimer-assignment'
														) }
													</Tag>
													<span>
														{ info.version }
													</span>
												</Space>
											) : (
												<Tag>
													{ __(
														'Not Detected',
														'pressprimer-assignment'
													) }
												</Tag>
											) }
										</td>
									</tr>
								)
							) }
						</tbody>
					</table>
				</div>
			) }

			{ /* File Handling Capabilities */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'File Handling', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Server capabilities for processing uploaded assignment files including security validation, text extraction, and document preview.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<table className="ppa-system-info">
					<tbody>
						<tr>
							<th>
								{ __(
									'MIME Detection (finfo)',
									'pressprimer-assignment'
								) }
							</th>
							<td>{ renderCapabilityTag( caps.finfo ) }</td>
						</tr>
						<tr>
							<th>
								{ __(
									'PDF Parser Library',
									'pressprimer-assignment'
								) }
							</th>
							<td>
								{ renderCapabilityTag( caps.pdfParserLibrary ) }
							</td>
						</tr>
						<tr>
							<th>
								{ __(
									'pdftotext Command',
									'pressprimer-assignment'
								) }
							</th>
							<td>
								{ caps.pdftotext ? (
									<Tag
										icon={ <CheckCircleOutlined /> }
										color="success"
									>
										{ __(
											'Available',
											'pressprimer-assignment'
										) }
									</Tag>
								) : (
									<Tag color="default">
										{ __(
											'Not Available',
											'pressprimer-assignment'
										) }
									</Tag>
								) }
							</td>
						</tr>
						<tr>
							<th>
								{ __(
									'DOCX Support (ZipArchive)',
									'pressprimer-assignment'
								) }
							</th>
							<td>{ renderCapabilityTag( caps.zipArchive ) }</td>
						</tr>
						<tr>
							<th>
								{ __(
									'Image Processing (GD)',
									'pressprimer-assignment'
								) }
							</th>
							<td>{ renderCapabilityTag( caps.gd ) }</td>
						</tr>
					</tbody>
				</table>
			</div>

			{ /* Database Tables */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'Database Tables', 'pressprimer-assignment' ) }
				</Title>

				{ hasMissingTables && (
					<Alert
						message={ __(
							'Missing Tables Detected',
							'pressprimer-assignment'
						) }
						description={ __(
							'Some database tables are missing. Click the repair button below to recreate them.',
							'pressprimer-assignment'
						) }
						type="warning"
						showIcon
						icon={ <ExclamationCircleOutlined /> }
						style={ { marginBottom: 16 } }
					/>
				) }

				<table className="ppa-system-info">
					<thead>
						<tr>
							<th>{ __( 'Table', 'pressprimer-assignment' ) }</th>
							<th>
								{ __( 'Status', 'pressprimer-assignment' ) }
							</th>
							<th>{ __( 'Rows', 'pressprimer-assignment' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ databaseTables.map( ( table, index ) => (
							<tr key={ index }>
								<td>
									<code>
										{ getShortTableName( table.name ) }
									</code>
								</td>
								<td>
									{ table.exists ? (
										<Tag
											icon={ <CheckCircleOutlined /> }
											color="success"
										>
											{ __(
												'OK',
												'pressprimer-assignment'
											) }
										</Tag>
									) : (
										<Tag
											icon={ <CloseCircleOutlined /> }
											color="error"
										>
											{ __(
												'Missing',
												'pressprimer-assignment'
											) }
										</Tag>
									) }
								</td>
								<td>
									{ table.exists
										? table.row_count.toLocaleString()
										: '\u2014' }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>

				{ hasMissingTables && (
					<div style={ { marginTop: 16 } }>
						<Button
							type="primary"
							danger
							icon={ <ToolOutlined /> }
							onClick={ handleRepairTables }
							loading={ isRepairing }
						>
							{ __(
								'Repair Database Tables',
								'pressprimer-assignment'
							) }
						</Button>
					</div>
				) }
			</div>

			{ /* Active Plugins */ }
			{ systemInfo.activePlugins &&
				systemInfo.activePlugins.length > 0 && (
					<div className="ppa-settings-section">
						<Title
							level={ 4 }
							className="ppa-settings-section-title"
						>
							{ sprintf(
								/* translators: %d: number of active plugins */
								__(
									'Active Plugins (%d)',
									'pressprimer-assignment'
								),
								systemInfo.activePlugins.length
							) }
						</Title>

						<div className="ppa-status-plugin-list">
							{ systemInfo.activePlugins.map(
								( plugin, index ) => (
									<span
										key={ index }
										className="ppa-status-plugin-item"
									>
										{ plugin }
									</span>
								)
							) }
						</div>
					</div>
				) }
		</div>
	);
};

export default StatusTab;

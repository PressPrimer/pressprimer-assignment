/**
 * General Tab Component
 *
 * Assignment defaults, page mapping, and general settings.
 *
 * @package
 * @since 1.0.0
 */

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Form, InputNumber, Select, Typography } from 'antd';

const { Title, Paragraph } = Typography;

/**
 * General Tab - Assignment defaults and page mapping
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.settings      Current settings.
 * @param {Function} props.updateSetting Function to update a setting.
 * @param {Object}   props.settingsData  Full settings data from PHP.
 */
const GeneralTab = ( { settings, updateSetting, settingsData } ) => {
	const pageOptions = useMemo( () => {
		const pages = settingsData.pages || [];
		return [
			{
				value: 0,
				label: __( '— Select a page —', 'pressprimer-assignment' ),
			},
			...pages.map( ( page ) => ( {
				value: page.id,
				label: page.title,
			} ) ),
		];
	}, [ settingsData.pages ] );

	return (
		<div>
			{ /* Page Mapping Section */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'Pages', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Select which pages contain assignment shortcodes. A "My Submissions" page is created automatically on activation.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'My Submissions Page',
							'pressprimer-assignment'
						) }
						help={ __(
							'Page containing the [ppa_my_submissions] shortcode. Used in email links so students can view their submissions.',
							'pressprimer-assignment'
						) }
					>
						<Select
							value={
								settings.my_submissions_page_id
									? Number( settings.my_submissions_page_id )
									: 0
							}
							onChange={ ( value ) =>
								updateSetting( 'my_submissions_page_id', value )
							}
							options={ pageOptions }
							style={ { width: 300 } }
							showSearch
							optionFilterProp="label"
						/>
					</Form.Item>
				</div>
			</div>

			{ /* Assignment Defaults Section */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'Assignment Defaults', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Default values used when creating new assignments.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'Default Passing Score',
							'pressprimer-assignment'
						) }
						help={ __(
							'Default points required to pass new assignments.',
							'pressprimer-assignment'
						) }
					>
						<InputNumber
							min={ 0 }
							max={ 100000 }
							value={ settings.default_passing_score ?? 60 }
							onChange={ ( value ) =>
								updateSetting( 'default_passing_score', value )
							}
							addonAfter={ __( 'pts', 'pressprimer-assignment' ) }
							style={ { width: 150 } }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'Default Max File Size',
							'pressprimer-assignment'
						) }
						help={ __(
							'Maximum file size for uploads (1-100 MB).',
							'pressprimer-assignment'
						) }
					>
						<InputNumber
							min={ 1 }
							max={ 100 }
							value={ settings.default_max_file_size ?? 10 }
							onChange={ ( value ) =>
								updateSetting( 'default_max_file_size', value )
							}
							addonAfter="MB"
							style={ { width: 150 } }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'Default Max Files',
							'pressprimer-assignment'
						) }
						help={ __(
							'Maximum number of files per submission (1-20).',
							'pressprimer-assignment'
						) }
					>
						<InputNumber
							min={ 1 }
							max={ 20 }
							value={ settings.default_max_files ?? 5 }
							onChange={ ( value ) =>
								updateSetting( 'default_max_files', value )
							}
							style={ { width: 150 } }
						/>
					</Form.Item>
				</div>
			</div>
		</div>
	);
};

export default GeneralTab;

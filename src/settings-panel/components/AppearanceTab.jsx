/**
 * Appearance Tab Component
 *
 * Global theme style settings that apply to all assignment themes.
 * Mirrors Quiz's Appearance tab: live preview, Collapse accordion,
 * matching ColorSetting pattern.
 *
 * The Live Preview faithfully reproduces the actual frontend assignment
 * page structure from templates/assignment/single.php and submission-form.php,
 * styled with exact values from assets/css/submission.css and theme CSS files.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Form,
	Select,
	Typography,
	ColorPicker,
	InputNumber,
	Space,
	Button,
	Card,
	Radio,
	Slider,
	Collapse,
} from 'antd';
import {
	UndoOutlined,
	FontSizeOutlined,
	BgColorsOutlined,
	LayoutOutlined,
	ColumnWidthOutlined,
} from '@ant-design/icons';

const { Paragraph, Text } = Typography;

/**
 * Theme-specific defaults — exact values from each theme CSS file.
 *
 * Every value here corresponds to a CSS custom property defined in
 * assets/css/themes/{name}.css so the preview matches the real frontend.
 */
const THEME_DEFAULTS = {
	default: {
		primary: '#0073aa',
		primaryHover: '#005a87',
		primaryLight: '#e5f3fa',
		text: '#1d2327',
		textSecondary: '#50575e',
		textLight: '#787c82',
		background: '#ffffff',
		backgroundGray: '#f6f7f7',
		success: '#00a32a',
		successLight: '#d8f4e0',
		error: '#d63638',
		errorLight: '#fce4e4',
		border: '#c3c4c7',
		borderLight: '#dcdcde',
		borderRadius: 6,
		radiusSm: 4,
		radiusMd: 6,
		radiusLg: 8,
		radiusXl: 12,
		shadowMd: '0 2px 4px rgba(0,0,0,0.07), 0 4px 8px rgba(0,0,0,0.05)',
		shadowLg: '0 4px 8px rgba(0,0,0,0.08), 0 8px 16px rgba(0,0,0,0.06)',
	},
	modern: {
		primary: '#4f46e5',
		primaryHover: '#4338ca',
		primaryLight: '#eef2ff',
		text: '#1e293b',
		textSecondary: '#475569',
		textLight: '#64748b',
		background: '#ffffff',
		backgroundGray: '#f8fafc',
		success: '#059669',
		successLight: '#d1fae5',
		error: '#dc2626',
		errorLight: '#fee2e2',
		border: '#e2e8f0',
		borderLight: '#f1f5f9',
		borderRadius: 10,
		radiusSm: 6,
		radiusMd: 10,
		radiusLg: 14,
		radiusXl: 18,
		shadowMd:
			'0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)',
		shadowLg:
			'0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)',
	},
	minimal: {
		primary: '#111827',
		primaryHover: '#1f2937',
		primaryLight: '#f3f4f6',
		text: '#111827',
		textSecondary: '#6b7280',
		textLight: '#9ca3af',
		background: '#ffffff',
		backgroundGray: '#fafafa',
		success: '#059669',
		successLight: '#ecfdf5',
		error: '#dc2626',
		errorLight: '#fef2f2',
		border: '#e5e7eb',
		borderLight: '#f3f4f6',
		borderRadius: 3,
		radiusSm: 2,
		radiusMd: 3,
		radiusLg: 4,
		radiusXl: 6,
		shadowMd: 'none',
		shadowLg: '0 1px 2px rgba(0,0,0,0.04)',
	},
};

/**
 * Spacing defaults
 */
const SPACING_DEFAULTS = {
	lineHeight: 1.5,
	maxWidth: 800,
};

/**
 * Font family options - stores CSS font-family values directly.
 */
const FONT_FAMILY_OPTIONS = [
	{
		value: '',
		label: __( 'Default', 'pressprimer-assignment' ),
	},
	{
		value: 'Georgia, "Times New Roman", Times, serif',
		label: __( 'Georgia (Serif)', 'pressprimer-assignment' ),
	},
	{
		value: '"Palatino Linotype", "Book Antiqua", Palatino, serif',
		label: __( 'Palatino (Serif)', 'pressprimer-assignment' ),
	},
	{
		value: 'Arial, Helvetica, sans-serif',
		label: __( 'Arial (Sans-serif)', 'pressprimer-assignment' ),
	},
	{
		value: 'Verdana, Geneva, sans-serif',
		label: __( 'Verdana (Sans-serif)', 'pressprimer-assignment' ),
	},
	{
		value: 'Tahoma, Geneva, sans-serif',
		label: __( 'Tahoma (Sans-serif)', 'pressprimer-assignment' ),
	},
	{
		value: '"Trebuchet MS", Helvetica, sans-serif',
		label: __( 'Trebuchet MS (Sans-serif)', 'pressprimer-assignment' ),
	},
	{
		value: '"Courier New", Courier, monospace',
		label: __( 'Courier New (Monospace)', 'pressprimer-assignment' ),
	},
];

/**
 * Font size options (base font size) - stores pixel strings.
 */
const FONT_SIZE_OPTIONS = [
	{
		value: '',
		label: __( 'Default (16px)', 'pressprimer-assignment' ),
	},
	{
		value: '14px',
		label: __( 'Small (14px)', 'pressprimer-assignment' ),
	},
	{
		value: '15px',
		label: __( 'Medium Small (15px)', 'pressprimer-assignment' ),
	},
	{
		value: '17px',
		label: __( 'Medium Large (17px)', 'pressprimer-assignment' ),
	},
	{
		value: '18px',
		label: __( 'Large (18px)', 'pressprimer-assignment' ),
	},
	{
		value: '20px',
		label: __( 'Extra Large (20px)', 'pressprimer-assignment' ),
	},
];

/**
 * Convert Ant Design color to hex string.
 *
 * @param {Object|string} color Ant Design color object or hex string.
 * @return {string} Hex color string.
 */
const colorToHex = ( color ) => {
	if ( ! color ) {
		return '';
	}
	if ( typeof color === 'string' ) {
		return color;
	}
	if ( color.toHexString ) {
		return color.toHexString();
	}
	return '';
};

/**
 * Color setting component with reset button.
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.label        Field label.
 * @param {string}   props.help         Help text.
 * @param {string}   props.value        Current hex value (empty = default).
 * @param {string}   props.defaultColor Default hex color.
 * @param {Function} props.onChange     Change handler.
 * @return {JSX.Element} Rendered component.
 */
const ColorSetting = ( { label, help, value, defaultColor, onChange } ) => {
	const hasCustomValue =
		value !== '' && value !== null && value !== undefined;
	const displayedColor = hasCustomValue ? value : defaultColor;

	return (
		<div className="ppa-settings-field">
			<Form.Item label={ label } help={ help }>
				<Space align="center" wrap>
					<ColorPicker
						value={ displayedColor }
						onChange={ ( color ) =>
							onChange( colorToHex( color ) )
						}
						disabledAlpha
						showText
					/>
					{ hasCustomValue ? (
						<Button
							type="link"
							icon={ <UndoOutlined /> }
							onClick={ () => onChange( '' ) }
							size="small"
						>
							{ __(
								'Reset to Default',
								'pressprimer-assignment'
							) }
						</Button>
					) : (
						<Text type="secondary">
							({ __( 'Default', 'pressprimer-assignment' ) })
						</Text>
					) }
				</Space>
			</Form.Item>
		</div>
	);
};

/**
 * Compact live preview of the assignment page.
 *
 * Reproduces the key frontend elements from submission-form.php
 * that are affected by appearance settings (colors, radius, shadows,
 * typography). Omits the title/description since they are simple
 * text and don't demonstrate the style overrides meaningfully.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.settings Current settings.
 * @return {JSX.Element} Rendered component.
 */
const AppearancePreview = ( { settings } ) => {
	const [ selectedTheme, setSelectedTheme ] = useState( 'default' );

	const t = useMemo( () => {
		const d = THEME_DEFAULTS[ selectedTheme ];

		const primary = settings.appearance_primary_color || d.primary;
		const text = settings.appearance_text_color || d.text;
		const bg = settings.appearance_background_color || d.background;
		const success = settings.appearance_success_color || d.success;
		const error = settings.appearance_error_color || d.error;

		const hasCustomRadius =
			settings.appearance_border_radius !== '' &&
			settings.appearance_border_radius !== null &&
			settings.appearance_border_radius !== undefined;
		const radiusMd = hasCustomRadius
			? settings.appearance_border_radius
			: d.radiusMd;
		const radiusLg = hasCustomRadius
			? Math.round( settings.appearance_border_radius * 1.33 )
			: d.radiusLg;

		const fontFamily =
			settings.appearance_font_family ||
			'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
		const fontSizeRaw = settings.appearance_font_size || '16px';
		const base = parseInt( fontSizeRaw, 10 ) || 16;
		const lineHeight =
			settings.appearance_line_height || SPACING_DEFAULTS.lineHeight;

		return {
			primary,
			text,
			bg,
			success,
			error,
			textSecondary: d.textSecondary,
			textLight: d.textLight,
			backgroundGray: d.backgroundGray,
			border: d.border,
			borderLight: d.borderLight,
			radiusMd,
			radiusLg,
			shadowMd: d.shadowMd,
			fontFamily,
			fontSize: base + 'px',
			fontSizeXs: Math.round( base * 0.75 ) + 'px',
			fontSizeSm: Math.round( base * 0.875 ) + 'px',
			fontSizeLg: Math.round( base * 1.125 ) + 'px',
			lineHeight,
		};
	}, [ settings, selectedTheme ] );

	return (
		<Card
			title={ __( 'Live Preview', 'pressprimer-assignment' ) }
			size="small"
			extra={
				<Radio.Group
					value={ selectedTheme }
					onChange={ ( e ) => setSelectedTheme( e.target.value ) }
					size="small"
					optionType="button"
					buttonStyle="solid"
				>
					<Radio.Button value="default">
						{ __( 'Default', 'pressprimer-assignment' ) }
					</Radio.Button>
					<Radio.Button value="modern">
						{ __( 'Modern', 'pressprimer-assignment' ) }
					</Radio.Button>
					<Radio.Button value="minimal">
						{ __( 'Minimal', 'pressprimer-assignment' ) }
					</Radio.Button>
				</Radio.Group>
			}
			style={ { marginBottom: '24px' } }
		>
			<Paragraph type="secondary" style={ { marginBottom: 16 } }>
				{ __(
					'Shows how your assignment page will look to students.',
					'pressprimer-assignment'
				) }
			</Paragraph>

			{ /* Outer background simulates page */ }
			<div
				style={ {
					background: '#f0f0f1',
					padding: '20px',
					borderRadius: '6px',
				} }
			>
				{ /* .ppa-assignment-content card */ }
				<div
					style={ {
						background: t.bg,
						border: `2px solid ${ t.border }`,
						borderRadius: `${ t.radiusLg }px`,
						boxShadow: t.shadowMd,
						padding: '24px',
						fontFamily: t.fontFamily,
						fontSize: t.fontSize,
						color: t.text,
						lineHeight: t.lineHeight,
					} }
					aria-live="polite"
					aria-label={ __(
						'Live preview of appearance settings',
						'pressprimer-assignment'
					) }
				>
					{ /* .ppa-assignment-meta bar */ }
					<div
						style={ {
							display: 'flex',
							flexWrap: 'wrap',
							gap: '10px',
							marginBottom: '16px',
							padding: '12px',
							background: t.backgroundGray,
							border: `1px solid ${ t.borderLight }`,
							borderRadius: `${ t.radiusLg }px`,
						} }
					>
						{ [
							{ label: 'Points', value: '100' },
							{ label: 'Passing Score', value: '60' },
							{ label: 'Max File Size', value: '10 MB' },
						].map( ( item ) => (
							<div
								key={ item.label }
								style={ {
									background: t.bg,
									border: `1px solid ${ t.borderLight }`,
									borderRadius: `${ t.radiusMd }px`,
									padding: '8px 12px',
									flex: '1 1 0',
									minWidth: 0,
								} }
							>
								<span
									style={ {
										fontSize: t.fontSizeXs,
										fontWeight: 600,
										textTransform: 'uppercase',
										letterSpacing: '0.05em',
										color: t.textLight,
										display: 'block',
										marginBottom: '1px',
									} }
								>
									{ item.label }
								</span>
								<span
									style={ {
										fontSize: t.fontSizeLg,
										fontWeight: 700,
										color: t.text,
									} }
								>
									{ item.value }
								</span>
							</div>
						) ) }
					</div>

					{ /* .ppa-assignment-instructions box */ }
					<div
						style={ {
							marginBottom: '16px',
							padding: '14px',
							background: t.bg,
							border: `1px solid ${ t.border }`,
							borderRadius: `${ t.radiusMd }px`,
						} }
					>
						<div
							style={ {
								fontSize: t.fontSizeLg,
								fontWeight: 600,
								color: t.text,
								marginBottom: '4px',
							} }
						>
							{ __( 'Instructions', 'pressprimer-assignment' ) }
						</div>
						<div
							style={ {
								fontSize: t.fontSize,
								color: t.textSecondary,
								lineHeight: 1.6,
							} }
						>
							{ __(
								'Submit a 2,000 word essay. Include a bibliography and proper citations.',
								'pressprimer-assignment'
							) }
						</div>
					</div>

					{ /* .ppa-upload-zone */ }
					<div
						style={ {
							fontSize: t.fontSize,
							fontWeight: 600,
							color: t.text,
							marginBottom: '6px',
						} }
					>
						{ __( 'Submit Your Work', 'pressprimer-assignment' ) }
					</div>
					<div
						style={ {
							border: `2px dashed ${ t.border }`,
							borderRadius: `${ t.radiusLg }px`,
							padding: '20px 16px',
							textAlign: 'center',
							background: t.backgroundGray,
							marginBottom: '10px',
						} }
					>
						{ /* dashicons-upload */ }
						<svg
							width="32"
							height="32"
							viewBox="0 0 20 20"
							fill={ t.textLight }
							style={ { marginBottom: '4px' } }
							aria-hidden="true"
						>
							<path d="M8 14V8H5l5-6 5 6h-3v6zm-2 4v-2h8v2z" />
						</svg>
						<div
							style={ {
								fontSize: t.fontSize,
								color: t.textSecondary,
								margin: '4px 0 0',
							} }
						>
							{ __(
								'Drag and drop files here, or click to browse',
								'pressprimer-assignment'
							) }
						</div>
						<div
							style={ {
								fontSize: t.fontSizeXs,
								color: t.textLight,
								marginTop: '2px',
							} }
						>
							{ __(
								'Accepted: PDF, DOCX, DOC, TXT, RTF, ODT, JPG, JPEG, PNG, GIF, ZIP (max 10 MB each, up to 5 files)',
								'pressprimer-assignment'
							) }
						</div>
					</div>

					{ /* .ppa-student-notes */ }
					<div
						style={ {
							fontSize: t.fontSize,
							fontWeight: 600,
							color: t.text,
							marginBottom: '4px',
						} }
					>
						{ __( 'Notes (optional)', 'pressprimer-assignment' ) }
					</div>
					<div
						style={ {
							fontSize: t.fontSizeSm,
							color: t.textLight,
							marginBottom: '4px',
						} }
					>
						{ __(
							'Add any context or comments about your submission.',
							'pressprimer-assignment'
						) }
					</div>
					<div
						style={ {
							width: '100%',
							height: '48px',
							padding: '8px 10px',
							border: `2px solid ${ t.border }`,
							borderRadius: `${ t.radiusMd }px`,
							fontFamily: t.fontFamily,
							fontSize: t.fontSizeSm,
							color: t.textLight,
							background: t.bg,
							boxSizing: 'border-box',
							marginBottom: '16px',
						} }
						aria-hidden="true"
					>
						{ __(
							'E.g., "I focused on the second approach\u2026"',
							'pressprimer-assignment'
						) }
					</div>

					{ /* Button row: Submit + badges */ }
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '8px',
							flexWrap: 'wrap',
						} }
					>
						<button
							type="button"
							style={ {
								fontFamily: 'inherit',
								fontWeight: 600,
								fontSize: t.fontSize,
								borderRadius: `${ t.radiusLg }px`,
								cursor: 'pointer',
								display: 'inline-flex',
								alignItems: 'center',
								justifyContent: 'center',
								border: 'none',
								minHeight: '34px',
								padding: '6px 18px',
								lineHeight: 1.5,
								whiteSpace: 'nowrap',
								background: t.primary,
								color: '#ffffff',
							} }
						>
							{ __(
								'Submit Assignment',
								'pressprimer-assignment'
							) }
						</button>
						<div style={ { flex: 1 } } />
						<span
							style={ {
								padding: '3px 12px',
								borderRadius: '9999px',
								fontWeight: 700,
								color: '#ffffff',
								fontSize: t.fontSizeXs,
								background: t.success,
							} }
						>
							{ __( 'Passed', 'pressprimer-assignment' ) }
						</span>
						<span
							style={ {
								padding: '3px 12px',
								borderRadius: '9999px',
								fontWeight: 700,
								color: '#ffffff',
								fontSize: t.fontSizeXs,
								background: t.error,
							} }
						>
							{ __( 'Failed', 'pressprimer-assignment' ) }
						</span>
					</div>
				</div>
			</div>
		</Card>
	);
};

/**
 * Appearance Tab - Global theme style settings
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.settings      Current settings.
 * @param {Function} props.updateSetting Function to update a setting.
 * @return {JSX.Element} Rendered component.
 */
const AppearanceTab = ( { settings, updateSetting } ) => {
	const currentTheme = settings.appearance_theme || 'default';
	const defaultColors = THEME_DEFAULTS.default;

	/**
	 * Get current spacing value with fallback to default.
	 *
	 * @param {string} baseName Setting base name.
	 * @return {number} Current value.
	 */
	const getCurrentSpacingValue = ( baseName ) => {
		const key = `appearance_${ baseName }`;
		const value = settings[ key ];
		if ( value !== undefined && value !== null && value !== '' ) {
			return value;
		}
		const defaultMap = {
			line_height: SPACING_DEFAULTS.lineHeight,
			max_width: SPACING_DEFAULTS.maxWidth,
		};
		return defaultMap[ baseName ];
	};

	const collapseItems = [
		{
			key: 'typography',
			label: (
				<Space>
					<FontSizeOutlined />
					{ __( 'Typography', 'pressprimer-assignment' ) }
				</Space>
			),
			children: (
				<div className="ppa-settings-section">
					<Paragraph className="ppa-settings-section-description">
						{ __(
							'Customize fonts for all assignment themes. These settings apply globally to Default, Modern, and Minimal themes.',
							'pressprimer-assignment'
						) }
					</Paragraph>

					<div className="ppa-settings-field">
						<Form.Item
							label={ __(
								'Font Family',
								'pressprimer-assignment'
							) }
							help={ __(
								'Choose a font family for assignment text.',
								'pressprimer-assignment'
							) }
						>
							<Select
								value={ settings.appearance_font_family || '' }
								onChange={ ( value ) =>
									updateSetting(
										'appearance_font_family',
										value
									)
								}
								style={ { width: 350 } }
								options={ FONT_FAMILY_OPTIONS }
							/>
						</Form.Item>
					</div>

					<div className="ppa-settings-field">
						<Form.Item
							label={ __(
								'Base Font Size',
								'pressprimer-assignment'
							) }
							help={ __(
								'The base font size for assignment content. Other sizes scale proportionally.',
								'pressprimer-assignment'
							) }
						>
							<Select
								value={ settings.appearance_font_size || '' }
								onChange={ ( value ) =>
									updateSetting(
										'appearance_font_size',
										value
									)
								}
								style={ { width: 350 } }
								options={ FONT_SIZE_OPTIONS }
							/>
						</Form.Item>
					</div>
				</div>
			),
		},
		{
			key: 'colors',
			label: (
				<Space>
					<BgColorsOutlined />
					{ __( 'Colors', 'pressprimer-assignment' ) }
				</Space>
			),
			children: (
				<div className="ppa-settings-section">
					<Paragraph className="ppa-settings-section-description">
						{ __(
							'Override theme colors globally. Custom colors apply to all themes. Use "Reset to Default" to restore theme-specific colors.',
							'pressprimer-assignment'
						) }
					</Paragraph>

					<ColorSetting
						label={ __(
							'Primary Color',
							'pressprimer-assignment'
						) }
						help={ __(
							'Used for buttons, links, and interactive elements.',
							'pressprimer-assignment'
						) }
						value={ settings.appearance_primary_color }
						defaultColor={ defaultColors.primary }
						onChange={ ( value ) =>
							updateSetting( 'appearance_primary_color', value )
						}
					/>

					<ColorSetting
						label={ __( 'Text Color', 'pressprimer-assignment' ) }
						help={ __(
							'Primary text color for assignment content.',
							'pressprimer-assignment'
						) }
						value={ settings.appearance_text_color }
						defaultColor={ defaultColors.text }
						onChange={ ( value ) =>
							updateSetting( 'appearance_text_color', value )
						}
					/>

					<ColorSetting
						label={ __(
							'Background Color',
							'pressprimer-assignment'
						) }
						help={ __(
							'Main background color for assignment containers.',
							'pressprimer-assignment'
						) }
						value={ settings.appearance_background_color }
						defaultColor={ defaultColors.background }
						onChange={ ( value ) =>
							updateSetting(
								'appearance_background_color',
								value
							)
						}
					/>

					<ColorSetting
						label={ __(
							'Success Color',
							'pressprimer-assignment'
						) }
						help={ __(
							'Color for pass badges and success notices.',
							'pressprimer-assignment'
						) }
						value={ settings.appearance_success_color }
						defaultColor={ defaultColors.success }
						onChange={ ( value ) =>
							updateSetting( 'appearance_success_color', value )
						}
					/>

					<ColorSetting
						label={ __( 'Error Color', 'pressprimer-assignment' ) }
						help={ __(
							'Color for fail badges and error notices.',
							'pressprimer-assignment'
						) }
						value={ settings.appearance_error_color }
						defaultColor={ defaultColors.error }
						onChange={ ( value ) =>
							updateSetting( 'appearance_error_color', value )
						}
					/>
				</div>
			),
		},
		{
			key: 'layout',
			label: (
				<Space>
					<LayoutOutlined />
					{ __( 'Layout', 'pressprimer-assignment' ) }
				</Space>
			),
			children: (
				<div className="ppa-settings-section">
					<Paragraph className="ppa-settings-section-description">
						{ __(
							'Adjust layout properties across all themes.',
							'pressprimer-assignment'
						) }
					</Paragraph>

					<div className="ppa-settings-field">
						<Form.Item
							label={ __(
								'Border Radius',
								'pressprimer-assignment'
							) }
							help={ __(
								'Roundness of corners. Set to 0 for sharp corners, higher values for more rounded. Leave empty for theme default.',
								'pressprimer-assignment'
							) }
						>
							<Space>
								<InputNumber
									min={ 0 }
									max={ 24 }
									value={
										settings.appearance_border_radius ??
										null
									}
									onChange={ ( value ) =>
										updateSetting(
											'appearance_border_radius',
											value
										)
									}
									addonAfter="px"
									placeholder={ __(
										'Default',
										'pressprimer-assignment'
									) }
									style={ { width: 150 } }
								/>
								{ settings.appearance_border_radius !== '' &&
									settings.appearance_border_radius !==
										null &&
									settings.appearance_border_radius !==
										undefined && (
										<Button
											type="link"
											icon={ <UndoOutlined /> }
											onClick={ () =>
												updateSetting(
													'appearance_border_radius',
													null
												)
											}
											size="small"
										>
											{ __(
												'Reset to Default',
												'pressprimer-assignment'
											) }
										</Button>
									) }
							</Space>
						</Form.Item>
					</div>
				</div>
			),
		},
		{
			key: 'spacing',
			label: (
				<Space>
					<ColumnWidthOutlined />
					{ __( 'Spacing', 'pressprimer-assignment' ) }
				</Space>
			),
			children: (
				<div className="ppa-settings-section">
					<Paragraph className="ppa-settings-section-description">
						{ __(
							'Fine-tune spacing for assignment elements.',
							'pressprimer-assignment'
						) }
					</Paragraph>

					<div className="ppa-settings-field">
						<Form.Item
							label={ __(
								'Line Height',
								'pressprimer-assignment'
							) }
							help={ __(
								'Controls the vertical spacing between lines of text.',
								'pressprimer-assignment'
							) }
						>
							<div
								style={ {
									display: 'flex',
									alignItems: 'center',
									gap: 16,
									maxWidth: 400,
								} }
							>
								<Slider
									min={ 1.2 }
									max={ 1.8 }
									step={ 0.1 }
									value={ getCurrentSpacingValue(
										'line_height'
									) }
									onChange={ ( value ) =>
										updateSetting(
											'appearance_line_height',
											value
										)
									}
									style={ { flex: 1 } }
									marks={ {
										1.2: '1.2',
										1.5: '1.5',
										1.8: '1.8',
									} }
								/>
								<Text
									style={ {
										minWidth: 40,
										textAlign: 'right',
									} }
								>
									{ getCurrentSpacingValue( 'line_height' ) }
								</Text>
							</div>
						</Form.Item>
					</div>

					<div className="ppa-settings-field">
						<Form.Item
							label={ __(
								'Container Max Width',
								'pressprimer-assignment'
							) }
							help={ __(
								'Maximum width of the assignment container. Set lower values for better readability.',
								'pressprimer-assignment'
							) }
						>
							<div
								style={ {
									display: 'flex',
									alignItems: 'center',
									gap: 16,
									maxWidth: 400,
								} }
							>
								<Slider
									min={ 400 }
									max={ 1200 }
									step={ 50 }
									value={ getCurrentSpacingValue(
										'max_width'
									) }
									onChange={ ( value ) =>
										updateSetting(
											'appearance_max_width',
											value
										)
									}
									style={ { flex: 1 } }
									marks={ {
										400: '400',
										800: '800',
										1200: '1200',
									} }
								/>
								<Text
									style={ {
										minWidth: 50,
										textAlign: 'right',
									} }
								>
									{ getCurrentSpacingValue( 'max_width' ) }px
								</Text>
							</div>
						</Form.Item>
					</div>
				</div>
			),
		},
	];

	return (
		<div>
			{ /* Default Theme Selector */ }
			<Card
				title={ __( 'Default Theme', 'pressprimer-assignment' ) }
				size="small"
				style={ { marginBottom: 24 } }
			>
				<Paragraph type="secondary">
					{ __(
						'Choose the default display theme for all assignments. Individual assignments can override this in their settings.',
						'pressprimer-assignment'
					) }
				</Paragraph>
				<Select
					value={ currentTheme }
					onChange={ ( value ) =>
						updateSetting( 'appearance_theme', value )
					}
					style={ { width: 300 } }
					options={ [
						{
							value: 'default',
							label: __( 'Default', 'pressprimer-assignment' ),
						},
						{
							value: 'modern',
							label: __( 'Modern', 'pressprimer-assignment' ),
						},
						{
							value: 'minimal',
							label: __( 'Minimal', 'pressprimer-assignment' ),
						},
					] }
				/>
			</Card>

			{ /* Live Preview */ }
			<AppearancePreview settings={ settings } />

			{ /* Collapsible Settings Sections */ }
			<Collapse
				defaultActiveKey={ [ 'typography' ] }
				items={ collapseItems }
				style={ { marginBottom: 24 } }
			/>
		</div>
	);
};

export default AppearanceTab;

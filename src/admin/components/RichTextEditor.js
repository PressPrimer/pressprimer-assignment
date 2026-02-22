/**
 * Rich Text Editor Component
 *
 * Simple rich text editor for assignment instructions and descriptions.
 * Uses a textarea with basic formatting for v1.0.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Input } from 'antd';

const { TextArea } = Input;

/**
 * RichTextEditor component.
 *
 * A textarea-based editor that accepts HTML content.
 * Can be replaced with a full WYSIWYG editor in future versions.
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.value       Current value.
 * @param {Function} props.onChange    Change handler.
 * @param {string}   props.placeholder Placeholder text.
 * @param {number}   props.rows        Number of visible rows.
 * @return {JSX.Element} Rich text editor.
 */
export default function RichTextEditor( {
	value,
	onChange,
	placeholder = __( 'Enter content\u2026', 'pressprimer-assignment' ),
	rows = 6,
} ) {
	return (
		<TextArea
			value={ value }
			onChange={ onChange }
			placeholder={ placeholder }
			rows={ rows }
			style={ { maxWidth: 500 } }
		/>
	);
}

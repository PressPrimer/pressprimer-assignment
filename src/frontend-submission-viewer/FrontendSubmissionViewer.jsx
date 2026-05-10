/**
 * Frontend submission viewer
 *
 * Thin wrapper around the shared DocumentPanel used on the admin side.
 * Receives `files` and `textContent` from PHP via wp_localize_script —
 * no REST calls are made for the document data itself, since the
 * containing template already has it. Annotations are loaded by the
 * School addon's AnnotationLayer override (when present), which is
 * registered onto window.PPADocumentViewerOverrides at bundle init.
 *
 * @package
 * @since 2.1.0
 */

import DocumentPanel from '../shared/components/viewers/DocumentPanel';

/**
 * FrontendSubmissionViewer.
 *
 * @param {Object}      props             Component props.
 * @param {Array}       props.files       Array of file objects (id, original_filename, file_extension, download_url, formatted_size, etc.).
 * @param {string|null} props.textContent Text-editor submission HTML, when present.
 * @param {number|null} props.wordCount   Word count for text submissions.
 * @return {JSX.Element} Rendered viewer.
 */
const FrontendSubmissionViewer = ( {
	files = [],
	textContent = null,
	wordCount = null,
} ) => {
	return (
		<DocumentPanel
			files={ files }
			textContent={ textContent }
			wordCount={ wordCount }
		/>
	);
};

export default FrontendSubmissionViewer;

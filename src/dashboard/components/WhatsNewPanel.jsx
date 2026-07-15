/**
 * What's New Panel Component
 *
 * The one-time post-update surface (011): release notes for the
 * latest major wave — one section per plugin, since the suite ships
 * coordinated releases — paired with the email ask and a blog link.
 * Shown to each admin once per wave: dismissing the 3.1 panel hides
 * it forever (including 3.1.x patches), while the next major's panel
 * shows fresh. Never on fresh installs. While the panel is up, the
 * dashboard email card yields (never stack asks).
 *
 * @package
 * @since 2.2.0
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { CloseOutlined, StarOutlined } from '@ant-design/icons';
import EmailOptinAsk from '../../shared/components/EmailOptinAsk';

/**
 * What's New Panel Component
 *
 * @param {Object} props            Component props.
 * @param {Object} props.whatsNew   Server-resolved panel data (show, version, sections, blogUrl, askEligible).
 * @param {Object} props.optin      Server-resolved opt-in data (privacyUrl).
 * @param {string} props.pluginName Plugin display name (white-label aware).
 * @return {JSX.Element|null} Rendered component.
 */
const WhatsNewPanel = ( { whatsNew = {}, optin = {}, pluginName } ) => {
	const [ hidden, setHidden ] = useState( false );

	const sections = Array.isArray( whatsNew.sections )
		? whatsNew.sections
		: [];

	if ( ! whatsNew.show || hidden || ! sections.length ) {
		return null;
	}

	/**
	 * Dismiss the panel — scoped to this wave for this admin.
	 */
	const handleDismiss = () => {
		setHidden( true );

		apiFetch( {
			path: '/ppa/v1/email-optin',
			method: 'POST',
			data: {
				decision: 'dismiss',
				source: 'whats-new',
			},
		} ).catch( () => {
			// Best-effort: the panel is already hidden for this view.
		} );
	};

	return (
		<div className="ppa-dashboard-card ppa-whats-new">
			<button
				type="button"
				className="ppa-dashboard-email-card__dismiss"
				onClick={ handleDismiss }
				aria-label={ __( 'Dismiss', 'pressprimer-assignment' ) }
			>
				<CloseOutlined />
			</button>

			<h3 className="ppa-whats-new__title">
				<StarOutlined />
				{ sprintf(
					/* translators: 1: plugin name, 2: major version line (e.g. 2.2) */
					__(
						"What's New in %1$s %2$s Releases",
						'pressprimer-assignment'
					),
					pluginName ||
						__(
							'PressPrimer Assignment',
							'pressprimer-assignment'
						),
					whatsNew.version || ''
				) }
			</h3>

			{ sections.map( ( section, sectionIndex ) => (
				<div className="ppa-whats-new__section" key={ sectionIndex }>
					{ section.title && (
						<h4 className="ppa-whats-new__section-title">
							{ section.title }
						</h4>
					) }
					<ul className="ppa-whats-new__notes">
						{ ( section.items || [] ).map( ( item, itemIndex ) => (
							<li key={ itemIndex }>{ item }</li>
						) ) }
					</ul>
				</div>
			) ) }

			{ whatsNew.blogUrl && (
				<p className="ppa-whats-new__read-more">
					<a
						href={ whatsNew.blogUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __(
							'Read more on the PressPrimer blog',
							'pressprimer-assignment'
						) }{ ' ' }
						&rarr;
					</a>
				</p>
			) }

			{ whatsNew.askEligible && (
				<div className="ppa-whats-new__ask">
					<EmailOptinAsk
						source="whats-new"
						privacyUrl={ optin.privacyUrl || '' }
					/>
				</div>
			) }
		</div>
	);
};

export default WhatsNewPanel;

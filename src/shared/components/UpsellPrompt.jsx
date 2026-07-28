/**
 * UpsellPrompt - Inline premium touchpoint
 *
 * Renders the one-sentence prompt + link for a touchpoint payload produced
 * by the server-side registry (PressPrimer_Assignment_Touchpoints). The
 * server only localizes touchpoints the current user is allowed to see
 * (providing addon inactive AND manage_options), so this component never
 * decides visibility — an absent payload means nothing renders. Copy and
 * link text arrive already translated from PHP.
 *
 * Contextual, not interruptive: one sentence, one link, no dismissal state,
 * never blocking. See docs/versions/v2.x/v2.2/features/012.
 *
 * @package
 * @since 2.2.0
 */

import { Typography } from 'antd';
import { LockOutlined } from '@ant-design/icons';

import './UpsellPrompt.css';

const { Text } = Typography;

/**
 * UpsellPrompt component
 *
 * @param {Object}      props            Component props.
 * @param {Object|null} props.touchpoint Touchpoint payload ({ key, copy, linkText, url }).
 * @param {boolean}     props.compact    Tighter padding for toolbar-like slots.
 * @param {Object|null} props.style      Optional inline style for the root element.
 * @return {JSX.Element|null} Rendered prompt or null.
 */
const UpsellPrompt = ( { touchpoint, compact = false, style = null } ) => {
	if ( ! touchpoint || ! touchpoint.copy || ! touchpoint.url ) {
		return null;
	}

	return (
		<div
			className={ `ppa-upsell-prompt${
				compact ? ' ppa-upsell-prompt--compact' : ''
			}` }
			style={ style || undefined }
			data-touchpoint={ touchpoint.key }
		>
			<LockOutlined
				className="ppa-upsell-prompt-icon"
				aria-hidden="true"
			/>
			<Text className="ppa-upsell-prompt-copy">{ touchpoint.copy }</Text>
			<a
				className="ppa-upsell-prompt-link"
				href={ touchpoint.url }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ touchpoint.linkText }
				<span aria-hidden="true"> →</span>
			</a>
		</div>
	);
};

export default UpsellPrompt;

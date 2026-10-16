import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { editorData } from './editor-data';
import './pro-upgrade-notice.css';

/** The editor's Pro upsell line: a Pro tag, `text`, then an Upgrade link to the upgrade page. */
export function ProUpgradeNotice( { text, className }: { text: string; className?: string } ) {
	return (
		<p className={ className }>
			<span className="yaycheckout-pro-tag">{ __( 'Pro', 'yaycheckout' ) }</span>
			{ text }{ ' ' }
			<ExternalLink href={ editorData.upgradeUrl }>{ __( 'Upgrade', 'yaycheckout' ) }</ExternalLink>
		</p>
	);
}

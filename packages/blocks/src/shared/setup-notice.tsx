import { ExternalLink, Notice } from '@wordpress/components';
import './setup-notice.css';

/** What BlockRegistry::editor_method_setup_data() prints for the Shipping methods and Payment blocks. */
export type MethodSetupData = { needsSetup: boolean; settingsUrl: string };

/** Warns that WooCommerce gives a block nothing to list, with a link to the settings that fix it. */
export function SetupNotice( { data, message, linkText }: { data: MethodSetupData; message: string; linkText: string } ) {
	if ( ! data.needsSetup ) {
		return null;
	}

	return (
		<Notice className="yaycheckout-setup-notice" status="warning" isDismissible={ false }>
			{ message }{ ' ' }
			{ data.settingsUrl !== '' && <ExternalLink href={ data.settingsUrl }>{ linkText }</ExternalLink> }
		</Notice>
	);
}

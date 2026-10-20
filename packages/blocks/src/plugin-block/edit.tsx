import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { PluginBlockCard } from './card';
import { FbtPreview } from './fbt-preview';
import { FreeShippingBarPreview } from './free-shipping-bar-preview';
import { OrderBumpPreview } from './order-bump-preview';
import { pluginBlocksData } from './plugin-blocks-data';
import { pluginBlocksStore } from './store';
import { useScopeClass } from '../shared/checkout-scope';

interface EditProps {
	attributes: { target: string };
}

/** Stand-ins for what a spot's shortcode prints, by target: the shortcode's markup with sample data. */
const PREVIEWS: Record< string, () => JSX.Element > = {
	'yayboost/order-bump': OrderBumpPreview,
	'yayboost/free-shipping-bar': FreeShippingBarPreview,
	'yayboost/frequently-bought-together': FbtPreview,
};

/**
 * A spot in the layout for another Yay plugin's feature (PluginBlockCatalogue). The storefront prints the entry's
 * shortcode, in either edition of the plugin; while it does, a target with a stand-in shows that, and the sidebar
 * links to the plugin's settings. Otherwise the card says what to do.
 */
export default function Edit( { attributes }: EditProps ) {
	const state = useSelect( ( select ) => select( pluginBlocksStore ).getPluginState( attributes.target )?.state, [ attributes.target ] );
	const Preview = state === 'shortcode' ? PREVIEWS[ attributes.target ] : undefined;
	const settingsUrl = pluginBlocksData.entries.find( ( entry ) => entry.target === attributes.target )?.settingsUrl ?? '';
	// It may sit outside Checkout (CheckoutScope).
	const blockProps = useBlockProps( { className: 'yaycheckout-plugin-block ' + useScopeClass() + ( Preview ? ' has-preview' : '' ) } );

	if ( ! Preview ) {
		return (
			<div { ...blockProps }>
				<PluginBlockCard target={ attributes.target } />
			</div>
		);
	}

	return (
		<>
			{ settingsUrl !== '' && (
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
						{ /* A new tab: the layout's unsaved changes stay in this one. */ }
						<Button variant="secondary" href={ settingsUrl } target="_blank" rel="noreferrer">
							{ __( 'Go to settings page', 'yaycheckout' ) }
						</Button>
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...blockProps }>
				<Preview />
			</div>
		</>
	);
}

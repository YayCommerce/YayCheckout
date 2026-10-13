/**
 * The block visibility setting in the editor. BlockRegistry enqueues it in every editor, so markup saved
 * in a layout stays valid anywhere.
 */
import { addFilter } from '@wordpress/hooks';
import { ATTRIBUTE, EXCLUDED } from '../shared/visibility-rules';

type BlockSettings = { attributes?: Record< string, unknown > };

addFilter( 'blocks.registerBlockType', 'yaycheckout/visibility', ( settings: BlockSettings, name: string ) =>
	EXCLUDED.includes( name ) ? settings : { ...settings, attributes: { ...settings.attributes, [ ATTRIBUTE ]: { type: 'object' } } }
);

import { decodeEntities } from '@wordpress/html-entities';
import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

interface EmailLabelAttributes {
	label?: string;
	metadata?: { name?: string };
}

registerDynamicBlock( metadata, Edit, undefined, {
	__experimentalLabel: ( attributes: EmailLabelAttributes, { context }: { context: string } ) => {
		if ( context !== 'list-view' ) {
			return undefined;
		}

		return attributes.metadata?.name || decodeEntities( attributes.label || '' ) || undefined;
	},
} );

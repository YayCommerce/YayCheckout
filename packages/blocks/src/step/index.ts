import { decodeEntities } from '@wordpress/html-entities';
import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import { stepTitle } from '../shared/step-title';

interface StepLabelAttributes {
	title?: string;
	metadata?: { name?: string };
}

registerDynamicBlock( metadata, Edit, saveNestedBlocks, {
	__experimentalLabel: ( attributes: StepLabelAttributes, { context }: { context: string } ) => {
		if ( context !== 'list-view' ) {
			return undefined;
		}

		return attributes.metadata?.name || decodeEntities( stepTitle( attributes.title ?? '' ) );
	},
} );

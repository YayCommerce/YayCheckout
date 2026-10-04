import { registerBlockVariation } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import { checkoutHooks } from './hooks';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata.name, Edit );

checkoutHooks.forEach( ( item ) => {
	registerBlockVariation( metadata.name, {
		name: item.name,
		title: item.title,
		description: item.hook,
		attributes: { hook: item.hook },
		isActive: ( attributes ) => attributes.hook === item.hook,
		scope: [ 'inserter' ],
	} );
} );

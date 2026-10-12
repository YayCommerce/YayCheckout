import metadata from './block.json';
import Edit from './edit';
import { listViewLabel, registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import { stepTitle } from '../shared/step-title';

registerDynamicBlock( metadata, Edit, saveNestedBlocks, {
	__experimentalLabel: listViewLabel( ( { title }: { title?: string } ) => stepTitle( title ?? '' ) ),
} );

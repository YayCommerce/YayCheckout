import metadata from './block.json';
import Edit from './edit';
import { stepIcon } from '../../shared/block-icons';
import { listViewLabel, registerDynamicBlock, saveNestedBlocks } from '../../shared/register-dynamic-block';
import { stepTitle } from '../../shared/step-title';

registerDynamicBlock( metadata, stepIcon, Edit, saveNestedBlocks, {
	__experimentalLabel: listViewLabel( ( { title }: { title?: string } ) => stepTitle( title ?? '' ) ),
} );

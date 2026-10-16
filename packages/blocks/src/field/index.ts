import metadata from './block.json';
import Edit from './edit';
import { fieldIcon } from '../shared/block-icons';
import { listViewLabel, registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, fieldIcon, Edit, undefined, {
	__experimentalLabel: listViewLabel( ( { label, fieldKey }: { label?: string; fieldKey?: string } ) => label || fieldKey || '' ),
} );

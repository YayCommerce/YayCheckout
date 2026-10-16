import metadata from './block.json';
import Edit from './edit';
import { extraFieldIcon } from '../shared/block-icons';
import { listViewLabel, registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, extraFieldIcon, Edit, undefined, {
	__experimentalLabel: listViewLabel( ( { label, fieldKey }: { label?: string; fieldKey?: string } ) => label || fieldKey || '' ),
} );

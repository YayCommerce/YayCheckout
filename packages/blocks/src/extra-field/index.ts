import metadata from './block.json';
import Edit from './edit';
import { listViewLabel, registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, Edit, undefined, {
	__experimentalLabel: listViewLabel( ( { label, fieldKey }: { label?: string; fieldKey?: string } ) => label || fieldKey || '' ),
} );

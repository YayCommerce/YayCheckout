import metadata from './block.json';
import Edit from './edit';
import { emailIcon } from '../shared/block-icons';
import { listViewLabel, registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, emailIcon, Edit, undefined, {
	__experimentalLabel: listViewLabel( ( { label }: { label?: string } ) => label || '' ),
} );

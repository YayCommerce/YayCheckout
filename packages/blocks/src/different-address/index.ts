import metadata from './block.json';
import Edit from './edit';
import { differentAddressIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, differentAddressIcon, Edit );

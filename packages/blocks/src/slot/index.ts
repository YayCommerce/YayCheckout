import metadata from './block.json';
import Edit from './edit';
import { slotIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, slotIcon, Edit );

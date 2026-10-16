import metadata from './block.json';
import Edit from './edit';
import { shippingIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, shippingIcon, Edit, saveNestedBlocks );

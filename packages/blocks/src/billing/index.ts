import metadata from './block.json';
import Edit from './edit';
import { billingIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, billingIcon, Edit, saveNestedBlocks );

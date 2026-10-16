import metadata from './block.json';
import Edit from './edit';
import { orderSummaryHeaderIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, orderSummaryHeaderIcon, Edit, saveNestedBlocks );

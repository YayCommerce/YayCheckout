import metadata from './block.json';
import Edit from './edit';
import { orderSummaryDetailsIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, orderSummaryDetailsIcon, Edit, saveNestedBlocks );

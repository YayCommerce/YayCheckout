import metadata from './block.json';
import Edit from './edit';
import { orderSummaryIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, orderSummaryIcon, Edit, saveNestedBlocks );

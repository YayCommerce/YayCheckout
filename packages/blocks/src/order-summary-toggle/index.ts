import metadata from './block.json';
import Edit from './edit';
import { orderSummaryToggleIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, orderSummaryToggleIcon, Edit, saveNestedBlocks );

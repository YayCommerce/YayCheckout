import metadata from './block.json';
import Edit from './edit';
import './style.css';
import { couponFormIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, couponFormIcon, Edit, saveNestedBlocks );

import metadata from './block.json';
import Edit from './edit';
import { returnToCartIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, returnToCartIcon, Edit );

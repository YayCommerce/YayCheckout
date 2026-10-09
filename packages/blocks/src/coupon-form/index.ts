import metadata from './block.json';
import Edit from './edit';
import './style.css';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, Edit, saveNestedBlocks );

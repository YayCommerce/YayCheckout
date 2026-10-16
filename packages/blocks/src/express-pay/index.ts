import metadata from './block.json';
import Edit from './edit';
import './style.css';
import { expressPayIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, expressPayIcon, Edit );

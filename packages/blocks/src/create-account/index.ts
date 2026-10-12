import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock } from '../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, Edit );

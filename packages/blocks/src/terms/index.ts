import metadata from './block.json';
import Edit from './edit';
import './style.css';
import { termsIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, termsIcon, Edit );

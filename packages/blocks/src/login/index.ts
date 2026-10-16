import './style.scss';
import './trigger-format';
import metadata from './block.json';
import Edit from './edit';
import { loginIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata, loginIcon, Edit );

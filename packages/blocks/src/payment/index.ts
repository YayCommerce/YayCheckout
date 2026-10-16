import metadata from './block.json';
import Edit from './edit';
import { paymentIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, paymentIcon, Edit );

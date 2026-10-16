import metadata from './block.json';
import Edit from './edit';
import { goToStepIcon } from '../../shared/block-icons';
import { registerDynamicBlock } from '../../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, goToStepIcon, Edit );

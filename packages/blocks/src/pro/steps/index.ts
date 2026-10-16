import metadata from './block.json';
import Edit from './edit';
import { stepsIcon } from '../../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, stepsIcon, Edit, saveNestedBlocks );

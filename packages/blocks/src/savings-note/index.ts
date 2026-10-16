import metadata from './block.json';
import Edit from './edit';
import { savingsNoteIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';
import './style.css';

registerDynamicBlock( metadata, savingsNoteIcon, Edit );

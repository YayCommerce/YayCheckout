import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata.name, Edit, saveNestedBlocks );

import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './insert-guard';
import './inserter-root';
import './publish-guard';

registerDynamicBlock( metadata.name, Edit, saveNestedBlocks );

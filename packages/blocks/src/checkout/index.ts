import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './insert-guard';
import './import-export';
import './inserter-root';

registerDynamicBlock( metadata.name, Edit, saveNestedBlocks );

import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './import-export';
import './inserter-root';
import './publish-guard';
import './style.css';

registerDynamicBlock( metadata, Edit, saveNestedBlocks );

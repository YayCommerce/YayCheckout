import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './import-export';
import './layout-design';
import './inserter-root';
import './layout-actions';
import './publish-guard';
import '../shared/hide-when-empty';

registerDynamicBlock( metadata, Edit, saveNestedBlocks );

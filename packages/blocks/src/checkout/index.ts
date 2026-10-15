import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './autosave-lock';
import './editor-tour';
import './import-export';
import './inserter-root';
import './layout-actions';
import './layout-design';
import './publish-guard';
import '../shared/pin-to-bottom';

registerDynamicBlock( metadata, Edit, saveNestedBlocks );

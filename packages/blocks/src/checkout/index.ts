import metadata from './block.json';
import Edit from './edit';
import { checkoutIcon } from '../shared/block-icons';
import { registerDynamicBlock, saveNestedBlocks } from '../shared/register-dynamic-block';
import './autosave-lock';
import './editor-tour';
import './import-export';
import './inserter-root';
import './layout-actions';
import './layout-design';
import './locked-pro-blocks';
import './publish-guard';
import '../shared/pin-to-bottom';

registerDynamicBlock( metadata, checkoutIcon, Edit, saveNestedBlocks );

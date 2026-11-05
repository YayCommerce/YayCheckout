import metadata from './block.json';
import Edit from './edit';
import { savePdfIcon } from '../../shared/block-icons';
import { editorData } from '../../shared/editor-data';
import { registerDynamicBlock } from '../../shared/register-dynamic-block';
import './style.css';

// Offered in the inserter on the Thank you view only; render.php prints nothing outside the thank-you page anyway.
registerDynamicBlock( metadata, savePdfIcon, Edit, undefined, {
	supports: { ...metadata.supports, inserter: editorData.view.current === 'thank-you' },
} );

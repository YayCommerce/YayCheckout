import metadata from './block.json';
import Edit, { INSERTABLE } from './edit';
import { thankYouTextIcon } from '../../shared/block-icons';
import { registerDynamicBlock } from '../../shared/register-dynamic-block';
import './style.css';

// Offered in the Thank you view only; ThankYouText::markup() prints nothing on any other page. It goes in the
// settings, not the metadata: core keeps the server's block.json definition over metadata passed here.
registerDynamicBlock( metadata, thankYouTextIcon, Edit, undefined, {
	supports: { ...metadata.supports, inserter: INSERTABLE },
} );

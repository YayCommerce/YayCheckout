import metadata from './block.json';
import Edit, { INSERTABLE } from './edit';
import { thankYouTextIcon } from '../../shared/block-icons';
import { registerDynamicBlock } from '../../shared/register-dynamic-block';
import './style.css';

// Offered in the Thank you view only; ThankYouText::markup() prints nothing on any other page.
registerDynamicBlock( { ...metadata, supports: { ...metadata.supports, inserter: INSERTABLE } }, thankYouTextIcon, Edit );

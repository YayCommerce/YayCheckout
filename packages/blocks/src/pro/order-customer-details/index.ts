import metadata from './block.json';
import Edit from './edit';
import { orderCustomerDetailsIcon } from '../../shared/block-icons';
import { registerDynamicBlock } from '../../shared/register-dynamic-block';

registerDynamicBlock( metadata, orderCustomerDetailsIcon, Edit );

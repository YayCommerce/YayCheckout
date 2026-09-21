import metadata from './block.json';
import Edit from './edit';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

registerDynamicBlock( metadata.name, Edit );

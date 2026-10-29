import backButtonMeta from '../pro/back-button/block.json';
import goToStepMeta from '../pro/go-to-step/block.json';
import orderCustomerDetailsMeta from '../pro/order-customer-details/block.json';
import orderDetailsMeta from '../pro/order-details/block.json';
import stepButtonMeta from '../pro/step-button/block.json';
import stepMeta from '../pro/step/block.json';
import stepsMeta from '../pro/steps/block.json';
import stepsProgressMeta from '../pro/steps-progress/block.json';

/**
 * The Pro blocks' metadata: the step blocks and the thank-you page's order blocks, built from packages/blocks/src/pro/ and left out of the Free
 * zip. Free registers them as locked stubs (checkout/locked-pro-blocks.tsx). Lists the folders under src/pro/,
 * as BlockRegistry::PRO_BLOCKS does; scripts/check-pro-boundary.sh fails when either differs from them.
 */
export const PRO_BLOCK_METAS = [
	stepsProgressMeta,
	stepsMeta,
	stepMeta,
	stepButtonMeta,
	backButtonMeta,
	goToStepMeta,
	orderDetailsMeta,
	orderCustomerDetailsMeta,
];

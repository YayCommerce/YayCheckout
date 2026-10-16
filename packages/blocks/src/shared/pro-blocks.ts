import backButtonMeta from '../pro/back-button/block.json';
import goToStepMeta from '../pro/go-to-step/block.json';
import stepButtonMeta from '../pro/step-button/block.json';
import stepMeta from '../pro/step/block.json';
import stepsMeta from '../pro/steps/block.json';
import stepsProgressMeta from '../pro/steps-progress/block.json';

/**
 * The Pro blocks' metadata: the step blocks, built from packages/blocks/src/pro/ and left out of the Free
 * zip. Free registers them as locked stubs (checkout/locked-pro-blocks.tsx). Mirrors BlockRegistry::PRO_BLOCKS.
 */
export const PRO_BLOCK_METAS = [
	stepsProgressMeta,
	stepsMeta,
	stepMeta,
	stepButtonMeta,
	backButtonMeta,
	goToStepMeta,
];

export const PRO_BLOCK_NAMES: string[] = PRO_BLOCK_METAS.map( ( meta ) => meta.name );

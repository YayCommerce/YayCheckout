import { store as blockEditorStore } from '@wordpress/block-editor';
import type { select as selectFn } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { KEY_ATTR, LIVE_VALUE_BLOCKS } from '../../live-value/constants';
import type { TourStep } from './steps';

/** A short tour of one feature, played the first time a user selects a block that has it. */
export type FeatureTour = {
	/** Kept in the user's list of seen tours, so renaming it plays the tour again for everyone. */
	id: string;
	/** Selecting one of these blocks starts the tour. */
	blocks: string[];
	steps: () => TourStep[];
};

/** The Live value toolbar button: it has no class of its own, and its label is translated. */
function liveValueButton(): string[] {
	return [ `.block-editor-block-toolbar button[aria-label="${ __( 'Live value', 'yaycheckout' ) }"]` ];
}

/** How many live values the selected block's text holds: rich text attributes print as their HTML. */
function selectedLiveValues( select: typeof selectFn ): number {
	const blocks = select( blockEditorStore );
	const clientId = blocks.getSelectedBlockClientId();

	if ( ! clientId ) {
		return 0;
	}

	return Object.values( blocks.getBlockAttributes( clientId ) ?? {} )
		.map( String )
		.join( '' )
		.split( KEY_ATTR ).length - 1;
}

const liveValueTour: FeatureTour = {
	id: 'live-value',
	blocks: LIVE_VALUE_BLOCKS,
	steps: () => [
		{
			id: 'live-value-intro',
			title: __( 'Show live values in your text', 'yaycheckout' ),
			content: __(
				"Live value puts the cart total, the number of items or a shopper's answer into this text. It updates on the checkout as the cart changes.",
				'yaycheckout'
			),
			fallback: __(
				"Live value puts the cart total, the number of items or a shopper's answer into a block's text. Select the text, then press Live value, the database icon in the block toolbar.",
				'yaycheckout'
			),
			target: liveValueButton(),
			placement: 'bottom-start',
		},
		{
			id: 'live-value-try',
			title: __( 'Try it', 'yaycheckout' ),
			content: __(
				'Press Live value, pick a value and press Insert. You can click the value later to change it.',
				'yaycheckout'
			),
			fallback: __(
				'Press Live value, the database icon in the block toolbar, pick a value and press Insert. You can click the value later to change it.',
				'yaycheckout'
			),
			target: liveValueButton(),
			placement: 'bottom-start',
			watch: selectedLiveValues,
		},
	],
};

/** Every feature's tour, in the order they are offered when one block has several. */
export const FEATURE_TOURS: FeatureTour[] = [ liveValueTour ];

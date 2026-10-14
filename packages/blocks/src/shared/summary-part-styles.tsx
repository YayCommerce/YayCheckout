import { __ } from '@wordpress/i18n';
import { textPartCSS, TextPartPanels, type TextPart } from './text-part-styles';
import type { StyleObject } from './style-path';

/**
 * The Label and Value styles of Order Summary Breakdown and Order Summary Total: core's typography
 * and text colour per part, under style.yaycheckout.<part>, compiled into one rule per cell type.
 * Mirrors SummaryPartStyles::wrapper_class() in PHP: the part keys, the printed keys and the cells.
 */

export type SummaryPart = 'label' | 'value';

/** Each part and the cell it styles. */
const PARTS: [ SummaryPart, 'th' | 'td' ][] = [
	[ 'label', 'th' ],
	[ 'value', 'td' ],
];

/** The Label and Value rules for the editor canvas, scoped to the block. */
export function summaryPartCSS( clientId: string, tableClass: string, style: StyleObject | undefined ): string {
	return PARTS.map( ( [ part, cell ] ) =>
		textPartCSS( style, part, `#block-${ clientId } table.${ tableClass } tr > ${ cell }` )
	).join( '' );
}

interface SummaryPartPanelsProps {
	style: StyleObject | undefined;
	onChange: ( style: StyleObject | undefined ) => void;
	/** False hides the Label panel, keeping its values. */
	showLabel?: boolean;
}

/** The Styles tab's Label and Value panels. */
export function SummaryPartPanels( { style, onChange, showLabel = true }: SummaryPartPanelsProps ) {
	const parts: TextPart[] = [
		...( showLabel ? [ { key: 'label', label: __( 'Label', 'yaycheckout' ) } ] : [] ),
		{ key: 'value', label: __( 'Value', 'yaycheckout' ) },
	];

	return <TextPartPanels parts={ parts } panelIdPrefix="yaycheckout-summary" style={ style } onChange={ onChange } />;
}

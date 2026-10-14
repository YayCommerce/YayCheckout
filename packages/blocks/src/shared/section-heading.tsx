import { BlockControls, HeadingLevelDropdown } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import type { StyleObject } from './style-path';
import { textPartCSS, TextPartPanels, type TextPart } from './text-part-styles';

/**
 * The heading level and the Title and Description styles of Billing, Shipping, Shipping Methods and Payment.
 * Mirrors SectionTextStyles in PHP: heading_tag()'s levels and default, and wrapper_class()'s parts and classes.
 */

export const HEADING_LEVELS = [ 1, 2, 3, 4, 5, 6 ];

const DEFAULT_LEVEL = 3;

export const TITLE_CLASS = 'yaycheckout-section-title';
export const DESCRIPTION_CLASS = 'yaycheckout-section-description';

/** A stored heading level, clamped to 1–6; 3 when missing or not a number. */
export function headingLevel( value: unknown ): number {
	const level = typeof value === 'number' ? value : parseInt( String( value ), 10 );

	return Number.isFinite( level ) ? Math.min( 6, Math.max( 1, Math.trunc( level ) ) ) : DEFAULT_LEVEL;
}

interface SectionHeadingToolbarProps {
	level: unknown;
	onChange: ( level: number ) => void;
}

/** The heading level picker in the block toolbar, like core's Heading. */
export function SectionHeadingToolbar( { level, onChange }: SectionHeadingToolbarProps ) {
	return (
		<BlockControls group="block">
			<HeadingLevelDropdown options={ HEADING_LEVELS } value={ headingLevel( level ) } onChange={ onChange } />
		</BlockControls>
	);
}

interface SectionTextPanelsProps {
	style: StyleObject | undefined;
	onChange: ( style: StyleObject | undefined ) => void;
	/** False hides the Title panel, keeping its values. */
	showTitle: boolean;
	/** False hides the Description panel, keeping its values. */
	showDescription: boolean;
}

/** The Styles tab's Title and Description panels. */
export function SectionTextPanels( { style, onChange, showTitle, showDescription }: SectionTextPanelsProps ) {
	const parts: TextPart[] = [
		...( showTitle ? [ { key: 'title', label: __( 'Title', 'yaycheckout' ) } ] : [] ),
		...( showDescription ? [ { key: 'description', label: __( 'Description', 'yaycheckout' ) } ] : [] ),
	];

	return <TextPartPanels parts={ parts } panelIdPrefix="yaycheckout-section" style={ style } onChange={ onChange } />;
}

/** The Title and Description rules for the editor canvas, scoped to the block. */
export function sectionTextCSS( clientId: string, style: StyleObject | undefined ): string {
	return (
		textPartCSS( style, 'title', `#block-${ clientId } > .${ TITLE_CLASS }` ) +
		textPartCSS( style, 'description', `#block-${ clientId } > .${ DESCRIPTION_CLASS }` )
	);
}

interface SectionTitleProps {
	level: unknown;
	text: string;
	className?: string;
}

/** The section heading at its level, as render.php prints it. */
export function SectionTitle( { level, text, className }: SectionTitleProps ) {
	const Tag = `h${ headingLevel( level ) }` as 'h3';

	return <Tag className={ className ? `${ TITLE_CLASS } ${ className }` : TITLE_CLASS }>{ text }</Tag>;
}

/** The section description, as render.php prints it; nothing while it is empty. */
export function SectionDescription( { text }: { text: string } ) {
	const trimmed = text.trim();

	return trimmed === '' ? null : <p className={ DESCRIPTION_CLASS }>{ trimmed }</p>;
}

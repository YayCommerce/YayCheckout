import { AlignmentControl, BlockControls, InspectorControls, PanelColorSettings, useBlockProps, Warning } from '@wordpress/block-editor';
import { __experimentalNumberControl as NumberControl, PanelBody, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import './editor.css';

type Slot = 'main' | 'secondary';
type Alignment = 'left' | 'center' | 'right';

interface Variant {
	id: string;
	slots: Slot[];
	svg: string;
}

interface Attributes {
	variant: string;
	useThemeColors: boolean;
	mainColor: string;
	secondaryColor: string;
	maxWidth?: number;
	alignment: Alignment;
}

interface EditProps {
	attributes: Attributes;
	setAttributes: ( attributes: Partial< Attributes > ) => void;
}

declare global {
	interface Window {
		yaycheckoutThankYouText?: { insertable: boolean; variants: Variant[] };
	}
}

/** Printed by ThankYouText::editor_data() before this script. */
const VARIANTS: Variant[] = window.yaycheckoutThankYouText?.variants ?? [];

/** Whether this editor is the layout's Thank you view, from ThankYouText::editor_data(). */
export const INSERTABLE = window.yaycheckoutThankYouText?.insertable ?? false;

/** Mirrors ThankYouText::DEFAULT_VARIANT. */
const DEFAULT_VARIANT = '1e';

/** Mirrors ThankYouText::THEME_COLORS. */
const THEME_COLORS: Record< Slot, string > = {
	main: 'var(--yaycheckout-design-primary)',
	secondary: 'var(--yaycheckout-design-text)',
};

const COLOR_ATTRIBUTES: Record< Slot, 'mainColor' | 'secondaryColor' > = {
	main: 'mainColor',
	secondary: 'secondaryColor',
};

function findVariant( id: string ): Variant | undefined {
	return VARIANTS.find( ( variant ) => variant.id === id ) ?? VARIANTS.find( ( variant ) => variant.id === DEFAULT_VARIANT );
}

/** Mirrors ThankYouText::markup(): the wrapper's classes and variables. */
function wrapperProps( attributes: Attributes, variant: Variant | undefined ) {
	const alignment = [ 'left', 'center', 'right' ].includes( attributes.alignment ) ? attributes.alignment : 'center';
	const classes = [ 'yaycheckout-thank-you-text', `is-align-${ alignment }` ];
	const style: Record< string, string > = {};

	for ( const slot of variant?.slots ?? [] ) {
		const color = attributes[ COLOR_ATTRIBUTES[ slot ] ] || ( attributes.useThemeColors ? THEME_COLORS[ slot ] : '' );

		if ( color ) {
			classes.push( `has-${ slot }-color` );
			style[ `--yaycheckout-thank-you-text-${ slot }` ] = color;
		}
	}

	if ( attributes.maxWidth && attributes.maxWidth > 0 ) {
		style[ '--yaycheckout-thank-you-text-max-width' ] = `${ attributes.maxWidth }px`;
	}

	return { className: classes.join( ' ' ), style: style as CSSProperties };
}

/**
 * The chosen artwork, as the storefront prints it. The sidebar picks the artwork, and for an artwork with
 * colour slots, theme or custom colours; an unset slot keeps the artwork's own colour.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const variant = findVariant( attributes.variant );
	const slots = variant?.slots ?? [];
	// Outside the Thank you view the canvas shows a warning instead, as the storefront prints nothing there.
	const blockProps = useBlockProps(
		INSERTABLE ? { ...wrapperProps( attributes, variant ), role: 'img', 'aria-label': __( 'Thank you', 'yaycheckout' ) } : {}
	);

	const colorSettings = slots.map( ( slot ) => ( {
		label: slot === 'main' ? __( 'Main', 'yaycheckout' ) : __( 'Secondary', 'yaycheckout' ),
		value: attributes[ COLOR_ATTRIBUTES[ slot ] ],
		onChange: ( value?: string ) => setAttributes( { [ COLOR_ATTRIBUTES[ slot ] ]: value ?? '' } ),
		clearable: true,
	} ) );

	return (
		<>
			<BlockControls group="block">
				<AlignmentControl
					value={ attributes.alignment }
					onChange={ ( alignment?: Alignment ) => setAttributes( { alignment: alignment ?? 'center' } ) }
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Style', 'yaycheckout' ) }>
					<div className="yaycheckout-thank-you-text-picker">
						{ VARIANTS.map( ( item ) => (
							<button
								key={ item.id }
								type="button"
								className="yaycheckout-thank-you-text-picker__item"
								aria-pressed={ item.id === variant?.id }
								aria-label={ sprintf(
									/* translators: %s: style id, such as 1e. */
									__( 'Style %s', 'yaycheckout' ),
									item.id
								) }
								onClick={ () => setAttributes( { variant: item.id } ) }
								dangerouslySetInnerHTML={ { __html: item.svg } }
							/>
						) ) }
					</div>
				</PanelBody>
				<PanelBody title={ __( 'Size', 'yaycheckout' ) }>
					<NumberControl
						label={ __( 'Max width (px)', 'yaycheckout' ) }
						help={ __( 'Leave empty for the style’s own width. It never grows wider than its container.', 'yaycheckout' ) }
						min={ 1 }
						value={ attributes.maxWidth ?? '' }
						onChange={ ( value?: string ) => {
							const width = parseInt( value ?? '', 10 );
							setAttributes( { maxWidth: width > 0 ? width : undefined } );
						} }
					/>
				</PanelBody>
			</InspectorControls>
			{ slots.length > 0 && (
				<InspectorControls group="styles">
					<PanelColorSettings title={ __( 'Colors', 'yaycheckout' ) } colorSettings={ colorSettings }>
						<ToggleControl
							label={ __( 'Use theme colors', 'yaycheckout' ) }
							help={
								slots.includes( 'secondary' )
									? __( 'Main follows the primary color and Secondary the text color, unless a color is picked above.', 'yaycheckout' )
									: __( 'Main follows the primary color, unless a color is picked above.', 'yaycheckout' )
							}
							checked={ attributes.useThemeColors }
							onChange={ ( useThemeColors: boolean ) => setAttributes( { useThemeColors } ) }
							__nextHasNoMarginBottom
						/>
					</PanelColorSettings>
				</InspectorControls>
			) }
			{ INSERTABLE ? (
				<div { ...blockProps } dangerouslySetInnerHTML={ { __html: variant?.svg ?? '' } } />
			) : (
				<div { ...blockProps }>
					<Warning>
						{ __( 'Thank You Text shows only on the thank-you page. Move it to the Thank you view, or remove it.', 'yaycheckout' ) }
					</Warning>
				</div>
			) }
		</>
	);
}

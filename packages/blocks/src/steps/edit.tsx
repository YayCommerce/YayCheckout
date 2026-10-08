import {
	__experimentalGetGapCSSValue as getGapCSSValue,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	useSettings,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import stepMeta from '../step/block.json';

interface GapStyle {
	spacing?: {
		blockGap?: unknown;
	};
}

interface StepsAttributes {
	layout: string;
	style?: GapStyle & Record< string, GapStyle | undefined >;
}

interface EditProps {
	attributes: StepsAttributes;
	setAttributes: ( attributes: Partial< StepsAttributes > ) => void;
	clientId: string;
}

interface ViewportSettings {
	mobile?: string;
	tablet?: string;
}

/**
 * Media queries for the viewport style states. Copies WP_Theme_JSON::get_viewport_media_queries()
 * and core's private JS getResponsiveMediaQueries(), without their breakpoint sanitizing.
 */
function viewportMediaQueries( viewport: ViewportSettings | undefined ): [ string, string ][] {
	const { mobile, tablet } = viewport?.mobile || viewport?.tablet ? viewport : { mobile: '480px', tablet: '782px' };
	const queries: [ string, string ][] = [];

	if ( mobile ) {
		queries.push( [ '@mobile', `@media (width <= ${ mobile })` ] );
	}

	if ( tablet ) {
		queries.push( [ '@tablet', mobile ? `@media (${ mobile } < width <= ${ tablet })` : `@media (width <= ${ tablet })` ] );
	}

	return queries;
}

/** A gap value as CSS, or null. Drops what wp_sanitize_block_gap_value() drops. */
function gapCSS( blockGap: unknown ): string | null {
	if ( typeof blockGap === 'string' && /[\\(&=}]|\/\*/.test( blockGap ) ) {
		return null;
	}

	return getGapCSSValue( blockGap );
}

/**
 * Canvas for checkout steps. Every step stays open here so its sections
 * can be edited; locking and Continue run on the storefront.
 */
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { layout, style } = attributes;
	const [ viewport ] = useSettings( 'viewport' );

	/*
	 * The block's own Block spacing and its Tablet / Mobile values, as render.php prints them. One
	 * stylesheet, since an inline gap would beat the media queries. The Global Styles gap is
	 * storefront-only: the editor keeps it behind a private API.
	 */
	const selector = `#block-${ clientId }`;
	const baseGap = gapCSS( style?.spacing?.blockGap );
	const css = [
		baseGap ? `${ selector }{gap:${ baseGap }}` : '',
		...viewportMediaQueries( viewport ).map( ( [ state, query ] ) => {
			const viewportGap = gapCSS( style?.[ state ]?.spacing?.blockGap );
			return viewportGap ? `${ query }{${ selector }{gap:${ viewportGap }}}` : '';
		} ),
	].join( '' );

	const blockProps = useBlockProps( { className: 'yaycheckout-steps' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: [ [ stepMeta.name ], [ stepMeta.name ] ],
		templateLock: false,
		defaultBlock: { name: stepMeta.name },
		directInsert: true,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Steps', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Layout', 'yaycheckout' ) }
						value={ layout || 'list' }
						options={ [
							{ label: __( 'List', 'yaycheckout' ), value: 'list' },
							{ label: __( 'One step', 'yaycheckout' ), value: 'single' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ /* Inside the wrapper, where a display:none child takes no flex gap; outside, it would shift the parent's sibling margins. */ }
			<div { ...innerBlocksProps }>
				{ css && <style>{ css }</style> }
				{ innerBlocksProps.children }
			</div>
		</>
	);
}

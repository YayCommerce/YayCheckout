import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import stepMeta from '../step/block.json';
import { useBlockGapCSS, type BlockGapStyles } from '../../shared/block-gap';
import { textPartCSS, TextPartPanels } from '../../shared/text-part-styles';

interface StepsAttributes {
	layout: string;
	showHeadings: boolean;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: StepsAttributes;
	setAttributes: ( attributes: Partial< StepsAttributes > ) => void;
	clientId: string;
}

/** Step header parts and their elements. Mirrors StepsTextStyles::PARTS in PHP. */
const HEADER_PARTS = [
	{ key: 'title', className: 'yaycheckout-step__title' },
	{ key: 'summary', className: 'yaycheckout-step__summary' },
];

/**
 * Canvas for checkout steps. Every step stays open here so its sections
 * can be edited; locking and Continue run on the storefront.
 */
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { layout, showHeadings, style } = attributes;
	// The canvas has no summary line (it fills in after Continue), so only the Title rule shows here.
	const css =
		useBlockGapCSS( `#block-${ clientId }`, style ) +
		HEADER_PARTS.map( ( { key, className } ) =>
			textPartCSS( style, key, `#block-${ clientId } .yaycheckout-step__header > .${ className }` )
		).join( '' );

	const blockProps = useBlockProps( { className: 'yaycheckout-steps', 'data-layout': layout } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: [ [ stepMeta.name ], [ stepMeta.name ] ],
		templateLock: false,
		defaultBlock: { name: stepMeta.name },
		directInsert: true,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Layout', 'yaycheckout' ) }
						value={ layout || 'accordion' }
						options={ [
							{ label: __( 'Accordion', 'yaycheckout' ), value: 'accordion' },
							{ label: __( 'One step at a time', 'yaycheckout' ), value: 'one-at-a-time' },
							{ label: __( 'Vertical timeline', 'yaycheckout' ), value: 'timeline' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show step headings', 'yaycheckout' ) }
						checked={ showHeadings }
						onChange={ ( value: boolean ) => setAttributes( { showHeadings: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<TextPartPanels
					parts={
						showHeadings
							? [
									{ key: 'title', label: __( 'Title', 'yaycheckout' ) },
									{ key: 'summary', label: __( 'Summary', 'yaycheckout' ) },
							  ]
							: []
					}
					panelIdPrefix="yaycheckout-steps"
					style={ style }
					onChange={ ( next ) => setAttributes( { style: next as BlockGapStyles | undefined } ) }
				/>
			</InspectorControls>
			{ /* Inside the wrapper, where a display:none child takes no flex gap; outside, it would shift the parent's sibling margins. */ }
			<div { ...innerBlocksProps }>
				{ css && <style>{ css }</style> }
				{ innerBlocksProps.children }
			</div>
		</>
	);
}

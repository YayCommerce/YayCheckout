import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import stepMeta from '../step/block.json';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';

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

/**
 * Canvas for checkout steps. Every step stays open here so its sections
 * can be edited; locking and Continue run on the storefront.
 */
export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { layout, showHeadings, style } = attributes;
	const css = useBlockGapCSS( `#block-${ clientId }`, style );

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
					<ToggleControl
						label={ __( 'Show step headings', 'yaycheckout' ) }
						checked={ showHeadings }
						onChange={ ( value: boolean ) => setAttributes( { showHeadings: value } ) }
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

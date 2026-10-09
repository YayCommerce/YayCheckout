import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	useSettings,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import stepMeta from '../step/block.json';
import { blockGapCSS, type BlockGapStyles } from '../shared/block-gap';

interface StepsAttributes {
	layout: string;
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
	const { layout, style } = attributes;
	const [ viewport ] = useSettings( 'viewport' );

	const css = blockGapCSS( `#block-${ clientId }`, style, viewport );

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

import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import stepMeta from '../step/block.json';

interface StepsAttributes {
	layout: string;
	style?: {
		spacing?: {
			blockGap?: unknown;
		};
	};
}

interface EditProps {
	attributes: StepsAttributes;
	setAttributes: ( attributes: Partial< StepsAttributes > ) => void;
}

/** Turns a core spacing preset or length into a CSS gap value. */
function gapValue( blockGap: unknown ): string | undefined {
	if ( typeof blockGap !== 'string' || blockGap === '' ) {
		return undefined;
	}

	const presetPrefix = 'var:preset|spacing|';
	if ( blockGap.startsWith( presetPrefix ) ) {
		return `var(--wp--preset--spacing--${ blockGap.slice( presetPrefix.length ) })`;
	}

	return blockGap;
}

/**
 * Canvas for checkout steps. Every step stays open here so its sections
 * can be edited; locking and Continue run on the storefront.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { layout } = attributes;
	const gap = gapValue( attributes.style?.spacing?.blockGap );
	const blockProps = useBlockProps( {
		className: 'yaycheckout-steps',
		...( gap !== undefined ? { style: { gap } } : {} ),
	} );
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
			<div { ...innerBlocksProps } />
		</>
	);
}

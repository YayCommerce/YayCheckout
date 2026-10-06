import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useContainerAppender } from '../shared/container-appender';
import stepMeta from '../step/block.json';

interface StepsAttributes {
	layout: string;
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
	const { layout } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-steps' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: [ [ stepMeta.name ], [ stepMeta.name ] ],
		templateLock: false,
		defaultBlock: { name: stepMeta.name },
		directInsert: true,
		renderAppender: useContainerAppender( clientId ),
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

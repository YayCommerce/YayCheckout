import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { checkoutHooks } from './hooks';

interface HookAttributes {
	hook: string;
}

interface EditProps {
	attributes: HookAttributes;
	setAttributes: ( attributes: Partial< HookAttributes > ) => void;
}

const frame = {
	minHeight: '48px',
	padding: '12px',
	border: '1px dashed #c3c4c7',
	color: '#646970',
	fontSize: '13px',
	textAlign: 'center' as const,
};

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { hook } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-hook' } );
	const current = checkoutHooks.find( ( item ) => item.hook === hook );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hook', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'WooCommerce hook', 'yaycheckout' ) }
						value={ hook }
						options={ checkoutHooks.map( ( item ) => ( {
							label: item.title,
							value: item.hook,
						} ) ) }
						onChange={ ( value ) => setAttributes( { hook: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps } style={ frame }>
				{ current ? current.title : hook }
			</div>
		</>
	);
}

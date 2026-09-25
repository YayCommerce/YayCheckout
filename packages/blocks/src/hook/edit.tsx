import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { checkoutHooks } from './hooks';

const CUSTOM = 'custom';

/** A hook name: lowercase letters, digits and underscores. */
function hookName( value: string ): string {
	return value.toLowerCase().trim().replace( /[^a-z0-9_]+/g, '_' ).replace( /^_+|_+$/g, '' );
}

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
	const selectValue = current ? hook : CUSTOM;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hook', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Hook', 'yaycheckout' ) }
						value={ selectValue }
						options={ [
							...checkoutHooks.map( ( item ) => ( {
								label: item.title,
								value: item.hook,
							} ) ),
							{ label: __( 'Custom', 'yaycheckout' ), value: CUSTOM },
						] }
						onChange={ ( value ) => setAttributes( { hook: value === CUSTOM ? '' : value } ) }
					/>
					{ selectValue === CUSTOM && (
						<TextControl
							label={ __( 'Custom hook', 'yaycheckout' ) }
							help={ __( 'Action name. Callbacks read checkout and cart from WC() themselves.', 'yaycheckout' ) }
							value={ hook }
							onChange={ ( value ) => setAttributes( { hook: hookName( value ) } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps } style={ frame }>
				{ current ? current.title : hook || __( 'Custom', 'yaycheckout' ) }
			</div>
		</>
	);
}

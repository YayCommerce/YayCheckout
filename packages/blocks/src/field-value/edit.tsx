import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { FormTokenField, PanelBody, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fieldNames, useShowableFields, type ShowableField } from '../shared/showable-fields';
import './editor.css';

interface FieldValueAttributes {
	fieldKeys: string[];
	separator: string;
	placeholder: string;
}

interface EditProps {
	attributes: FieldValueAttributes;
	setAttributes: ( attributes: Partial< FieldValueAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { fieldKeys, separator, placeholder } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-field-value' } );
	const fields = useShowableFields();
	const picked = fieldKeys
		.map( ( key ) => fields.find( ( candidate ) => candidate.key === key ) )
		.filter( ( field ): field is ShowableField => field !== undefined );
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch( blockEditorStore );

	// A field left the layout: drop it, so the storefront never reads a field the layout lost.
	useEffect( () => {
		if ( picked.length !== fieldKeys.length ) {
			markNotPersistent();
			setAttributes( {
				fieldKeys: picked.map( ( field ) => field.key ),
				...( picked.length === 0 ? { placeholder: '' } : {} ),
			} );
		}
	}, [ fieldKeys, picked.length ] );

	const names = useMemo( () => fieldNames( fields ), [ fields ] );
	const keyOf = useMemo( () => new Map( [ ...names ].map( ( [ key, name ] ) => [ name, key ] ) ), [ names ] );
	const labels = picked.map( ( field ) => field.label ).join( separator );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field value', 'yaycheckout' ) }>
					{ /* Values print in the order the fields were added; remove and re-add one to move it. */ }
					<FormTokenField
						label={ __( 'Fields', 'yaycheckout' ) }
						value={ picked.map( ( field ) => names.get( field.key ) ?? field.key ) }
						suggestions={ [ ...names.values() ] }
						// A placeholder is written for its fields ("email@____"), so a new set starts from their names.
						onChange={ ( tokens ) =>
							setAttributes( {
								fieldKeys: tokens
									.map( ( token ) => keyOf.get( typeof token === 'string' ? token : token.value ) )
									.filter( ( key ): key is string => key !== undefined ),
								placeholder: '',
							} )
						}
						__experimentalValidateInput={ ( token: string ) => keyOf.has( token ) }
						__experimentalExpandOnFocus
						__experimentalShowHowTo={ false }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					{ fields.length === 0 && (
						<p className="components-base-control__help">
							{ __( 'Add an Email address, Field or Extra Field block to the layout first.', 'yaycheckout' ) }
						</p>
					) }
					{ fieldKeys.length > 1 && (
						<TextControl
							label={ __( 'Separator', 'yaycheckout' ) }
							help={ __( 'Printed between the values. Spaces count.', 'yaycheckout' ) }
							value={ separator }
							onChange={ ( value: string ) => setAttributes( { separator: value } ) }
							__nextHasNoMarginBottom
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ /* The muted text the shopper sees until they fill a field; empty falls back to the fields' labels. */ }
				{ picked.length > 0 ? (
					<RichText
						tagName="span"
						className="yaycheckout-field-value__value is-empty"
						value={ placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
						placeholder={ labels }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				) : (
					<span className="yaycheckout-field-value__value is-empty">
						{ __( 'Select a field in the block settings. Nothing shows on the storefront until then.', 'yaycheckout' ) }
					</span>
				) }
			</div>
		</>
	);
}

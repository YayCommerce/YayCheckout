import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { ArrowControl } from '../shared/arrow-control';
import { arrowGlyph, type Arrow } from '../shared/button-arrow';
import { StepNavButtonEdit, type StepNavButtonAttributes } from '../shared/step-nav-button-edit';

type FirstStep = 'cart' | 'hide';

interface BackButtonAttributes extends StepNavButtonAttributes {
	firstStep: FirstStep;
	cartText: string;
	arrow: Arrow;
}

interface EditProps {
	attributes: BackButtonAttributes;
	setAttributes: ( attributes: Partial< BackButtonAttributes > ) => void;
}

// The placeholders mirror the fallback labels in back-button/render.php.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Previous step button', 'yaycheckout' ) }>
					<ArrowControl direction="back" value={ attributes.arrow } onChange={ ( arrow ) => setAttributes( { arrow } ) } />
					<SelectControl
						label={ __( 'On the first step', 'yaycheckout' ) }
						value={ attributes.firstStep }
						options={ [
							{ label: __( 'Return to cart', 'yaycheckout' ), value: 'cart' },
							{ label: __( 'Hide', 'yaycheckout' ), value: 'hide' },
						] }
						onChange={ ( value: string ) => setAttributes( { firstStep: value as FirstStep } ) }
					/>
					{ attributes.firstStep !== 'hide' && (
						<TextControl
							label={ __( 'Cart button text', 'yaycheckout' ) }
							value={ decodeEntities( attributes.cartText ) }
							placeholder={ __( 'Return to cart', 'yaycheckout' ) }
							onChange={ ( value ) => setAttributes( { cartText: value } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<StepNavButtonEdit
				attributes={ attributes }
				setAttributes={ setAttributes }
				className="yaycheckout-back-button"
				placeholder={ __( 'Back', 'yaycheckout' ) }
				icon={ arrowGlyph( attributes.arrow, 'back' ) }
				iconPosition="before"
			/>
		</>
	);
}

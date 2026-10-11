import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { arrowGlyph, arrowOptions, type Arrow } from '../shared/button-arrow';
import { StepNavButtonEdit, type StepNavButtonAttributes } from '../shared/step-nav-button-edit';

interface StepButtonAttributes extends StepNavButtonAttributes {
	arrow: Arrow;
}

interface EditProps {
	attributes: StepButtonAttributes;
	setAttributes: ( attributes: Partial< StepButtonAttributes > ) => void;
}

// The placeholder mirrors the fallback label in step-button/render.php.
export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Continue button', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Icon', 'yaycheckout' ) }
						value={ attributes.arrow }
						options={ arrowOptions( 'forward' ) }
						onChange={ ( value: string ) => setAttributes( { arrow: value as Arrow } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<StepNavButtonEdit
				attributes={ attributes }
				setAttributes={ setAttributes }
				className="yaycheckout-step-button"
				placeholder={ __( 'Continue', 'yaycheckout' ) }
				icon={ arrowGlyph( attributes.arrow, 'forward' ) }
				iconPosition="after"
				iconClassName="yaycheckout-step-button__icon"
			/>
		</>
	);
}

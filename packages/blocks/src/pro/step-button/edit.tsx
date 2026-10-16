import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ArrowControl } from '../../shared/arrow-control';
import { arrowGlyph, type Arrow } from '../../shared/button-arrow';
import { StepNavButtonEdit, type StepNavButtonAttributes } from '../../shared/step-nav-button-edit';

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
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ArrowControl direction="forward" value={ attributes.arrow } onChange={ ( arrow ) => setAttributes( { arrow } ) } />
				</PanelBody>
			</InspectorControls>
			<StepNavButtonEdit
				attributes={ attributes }
				setAttributes={ setAttributes }
				className="yaycheckout-step-button"
				placeholder={ __( 'Continue', 'yaycheckout' ) }
				icon={ arrowGlyph( attributes.arrow, 'forward' ) }
			/>
		</>
	);
}

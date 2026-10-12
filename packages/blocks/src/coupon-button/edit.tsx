import { __ } from '@wordpress/i18n';
import { StepNavButtonEdit, type StepNavButtonEditProps } from '../shared/step-nav-button-edit';

/** The button is the block's root element, so its style supports land on it. Mirrors the classes and default label render.php prints. */
export default function Edit( props: StepNavButtonEditProps ) {
	return (
		<StepNavButtonEdit
			{ ...props }
			className="button wp-element-button yaycheckout-coupon-form__apply"
			placeholder={ __( 'Apply coupon', 'yaycheckout' ) }
		/>
	);
}

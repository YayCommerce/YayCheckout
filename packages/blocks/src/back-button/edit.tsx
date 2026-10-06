import { __ } from '@wordpress/i18n';
import { StepNavButtonEdit, type StepNavButtonEditProps } from '../shared/step-nav-button-edit';

// The placeholder mirrors the fallback label in back-button/render.php.
export default function Edit( props: StepNavButtonEditProps ) {
	return <StepNavButtonEdit { ...props } className="yaycheckout-back-button" placeholder={ __( 'Back', 'yaycheckout' ) } />;
}

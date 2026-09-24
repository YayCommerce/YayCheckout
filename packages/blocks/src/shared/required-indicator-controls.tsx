import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface RequiredIndicatorControlsProps {
	required: boolean;
	requiredMark: string;
	optionalText: string;
	requiredLocked?: boolean;
	requiredHelp?: string;
	onRequiredChange?: ( value: boolean ) => void;
	onRequiredMarkChange: ( value: string ) => void;
	onOptionalTextChange?: ( value: string ) => void;
}

/**
 * Required toggle plus the mark / optional-text that follow it. One control
 * at a time so the unused string is not shown, but both values stay stored.
 */
export function RequiredIndicatorControls( {
	required,
	requiredMark,
	optionalText,
	requiredLocked = false,
	requiredHelp,
	onRequiredChange,
	onRequiredMarkChange,
	onOptionalTextChange,
}: RequiredIndicatorControlsProps ) {
	return (
		<>
			<ToggleControl
				label={ __( 'Required', 'yaycheckout' ) }
				checked={ required }
				disabled={ requiredLocked }
				help={ requiredHelp }
				onChange={ onRequiredChange ?? ( () => {} ) }
			/>
			{ required ? (
				<TextControl
					label={ __( 'Required mark', 'yaycheckout' ) }
					value={ requiredMark }
					placeholder="*"
					help={ __( 'Leave empty to use the WooCommerce default.', 'yaycheckout' ) }
					onChange={ onRequiredMarkChange }
				/>
			) : (
				<TextControl
					label={ __( 'Optional text', 'yaycheckout' ) }
					value={ optionalText }
					placeholder={ __( 'optional', 'yaycheckout' ) }
					help={ __(
						'Leave empty to use the WooCommerce default. Shown in parentheses.',
						'yaycheckout'
					) }
					onChange={ onOptionalTextChange ?? ( () => {} ) }
				/>
			) }
		</>
	);
}

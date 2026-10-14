import { RichText } from '@wordpress/block-editor';
import './field-preview.css';

export function previewInputType( type: string ): string {
	if ( [ 'email', 'tel', 'password' ].includes( type ) ) {
		return type;
	}

	// Mirrors $yaycheckout_woo_type in packages/blocks/src/extra-field/render.php.
	if ( type === 'datetime' ) {
		return 'datetime-local';
	}

	return 'text';
}

export interface FieldOption {
	label: string;
	value: string;
}

interface FieldPreviewProps {
	label: string;
	type: string;
	required?: boolean;
	requiredMark?: string;
	optionalText?: string;
	placeholder?: string;
	/** Help text, shown under the control. */
	description?: string;
	options?: FieldOption[];
	/** The block's own typography styles the label. */
	inheritLabelFont?: boolean;
	/** Makes the label editable on the canvas; labelPlaceholder shows while it is empty. */
	onLabelChange?: ( value: string ) => void;
	labelPlaceholder?: string;
	/** Shown in place of a Select or Radio with no usable options; without it, the label stands in as the one choice. */
	emptyOptionsHint?: string;
}

/** Read-only so typing on the canvas does not fight block selection. */
export function FieldPreview( {
	label,
	type,
	required = false,
	requiredMark = '',
	optionalText = '',
	placeholder = '',
	description = '',
	options = [],
	inheritLabelFont = false,
	onLabelChange,
	labelPlaceholder = '',
	emptyOptionsHint = '',
}: FieldPreviewProps ) {
	const LabelTag = onLabelChange ? 'div' : 'label';

	const usableOptions = options.filter( ( option ) => option.label !== '' || option.value !== '' );
	const showEmptyOptionsHint = emptyOptionsHint !== '' && usableOptions.length === 0 && [ 'select', 'radio' ].includes( type );

	return (
		<div className="yaycheckout-field-preview form-row">
			<LabelTag
				className={
					'yaycheckout-field-preview__label' +
					( inheritLabelFont ? ' yaycheckout-field-preview__label--inherit' : '' )
				}
			>
				{ onLabelChange ? (
					<RichText
						tagName="span"
						value={ label }
						onChange={ onLabelChange }
						placeholder={ labelPlaceholder }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				) : (
					label
				) }
				{ required ? (
					<span className="required">{ requiredMark || '*' }</span>
				) : (
					<span className="optional">
						({ optionalText || 'optional' })
					</span>
				) }
			</LabelTag>
			{ showEmptyOptionsHint ? (
				<p className="yaycheckout-field-preview__empty-options">{ emptyOptionsHint }</p>
			) : type === 'textarea' ? (
				<textarea readOnly rows={ 3 } placeholder={ placeholder } className="input-text" />
			) : type === 'select' ? (
				<select disabled>
					{ usableOptions.length === 0 ? (
						<option>{ placeholder || label || labelPlaceholder }</option>
					) : (
						usableOptions.map( ( option, index ) => (
							<option key={ index } value={ option.value }>
								{ option.label || option.value }
							</option>
						) )
					) }
				</select>
			) : type === 'radio' ? (
				<div className="yaycheckout-field-preview__choices">
					{ usableOptions.length === 0 ? (
						<label className="yaycheckout-field-preview__choice">
							<input type="radio" disabled /> { placeholder || label || labelPlaceholder }
						</label>
					) : (
						usableOptions.map( ( option, index ) => (
							<label key={ index } className="yaycheckout-field-preview__choice">
								<input type="radio" disabled /> { option.label || option.value }
							</label>
						) )
					) }
				</div>
			) : type === 'checkbox' && usableOptions.length > 0 ? (
				<div className="yaycheckout-field-preview__choices">
					{ usableOptions.map( ( option, index ) => (
						<label key={ index } className="yaycheckout-field-preview__choice">
							<input type="checkbox" disabled /> { option.label || option.value }
						</label>
					) ) }
				</div>
			) : type === 'checkbox' ? (
				<input type="checkbox" disabled className="yaycheckout-field-preview__check" />
			) : type === 'hidden' ? (
				<span className="yaycheckout-field-preview__hidden">{ label || labelPlaceholder }</span>
			) : (
				<input
					type={ previewInputType( type ) }
					readOnly
					placeholder={ placeholder }
					className="input-text"
				/>
			) }
			{ description !== '' && <p className="description">{ description }</p> }
		</div>
	);
}

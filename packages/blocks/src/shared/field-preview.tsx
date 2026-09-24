import type { CSSProperties } from 'react';

export function previewInputType( type: string ): string {
	if ( [ 'email', 'tel', 'password' ].includes( type ) ) {
		return type;
	}

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
	options?: FieldOption[];
	labelStyle?: CSSProperties;
}

const inputStyle = {
	display: 'block',
	width: '100%',
	boxSizing: 'border-box' as const,
	marginTop: '4px',
	padding: '8px 10px',
	border: '1px solid #8c8f94',
	borderRadius: '4px',
	background: '#fff',
};

/**
 * Read-only canvas control. Typing here must not fight Gutenberg selection.
 */
export function FieldPreview( {
	label,
	type,
	required = false,
	requiredMark = '',
	optionalText = '',
	placeholder = '',
	options = [],
	labelStyle,
}: FieldPreviewProps ) {
	const usableOptions = options.filter( ( option ) => option.label !== '' || option.value !== '' );

	return (
		<div style={ { marginBottom: '12px' } }>
			<label style={ { display: 'block', fontSize: '13px', fontWeight: 600, ...labelStyle } }>
				{ label }
				{ required ? (
					<span style={ { color: '#cc1818', marginLeft: '4px' } }>{ requiredMark || '*' }</span>
				) : (
					<span style={ { color: '#646970', fontWeight: 400, marginLeft: '4px' } }>
						({ optionalText || 'optional' })
					</span>
				) }
			</label>
			{ type === 'textarea' ? (
				<textarea readOnly rows={ 3 } placeholder={ placeholder } style={ inputStyle } />
			) : type === 'select' ? (
				<select disabled style={ inputStyle }>
					{ usableOptions.length === 0 ? (
						<option>{ placeholder || label }</option>
					) : (
						usableOptions.map( ( option, index ) => (
							<option key={ index } value={ option.value }>
								{ option.label || option.value }
							</option>
						) )
					) }
				</select>
			) : type === 'radio' ? (
				<div style={ { marginTop: '8px' } }>
					{ usableOptions.length === 0 ? (
						<label style={ { display: 'block' } }>
							<input type="radio" disabled /> { placeholder || label }
						</label>
					) : (
						usableOptions.map( ( option, index ) => (
							<label key={ index } style={ { display: 'block', marginBottom: '4px' } }>
								<input type="radio" disabled /> { option.label || option.value }
							</label>
						) )
					) }
				</div>
			) : type === 'checkbox' && usableOptions.length > 0 ? (
				<div style={ { marginTop: '8px' } }>
					{ usableOptions.map( ( option, index ) => (
						<label key={ index } style={ { display: 'block', marginBottom: '4px' } }>
							<input type="checkbox" disabled /> { option.label || option.value }
						</label>
					) ) }
				</div>
			) : type === 'checkbox' ? (
				<input type="checkbox" disabled style={ { marginTop: '8px' } } />
			) : type === 'hidden' ? (
				<span style={ { color: '#646970', fontSize: '12px' } }>{ label }</span>
			) : (
				<input
					type={ previewInputType( type ) }
					readOnly
					placeholder={ type === 'datetime' ? placeholder || 'YYYY-MM-DD HH:mm' : placeholder }
					style={ inputStyle }
				/>
			) }
		</div>
	);
}

export function previewInputType( type: string ): string {
	if ( [ 'email', 'tel', 'password' ].includes( type ) ) {
		return type;
	}

	return 'text';
}

interface FieldPreviewProps {
	label: string;
	type: string;
	required?: boolean;
	placeholder?: string;
}

/**
 * Read-only canvas control. Typing here must not fight Gutenberg selection.
 */
export function FieldPreview( {
	label,
	type,
	required = false,
	placeholder = '',
}: FieldPreviewProps ) {
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

	return (
		<div style={ { marginBottom: '12px' } }>
			<label style={ { display: 'block', fontSize: '13px', fontWeight: 600 } }>
				{ label }
				{ required && <span style={ { color: '#cc1818', marginLeft: '4px' } }>*</span> }
			</label>
			{ type === 'textarea' ? (
				<textarea readOnly rows={ 3 } placeholder={ placeholder } style={ inputStyle } />
			) : type === 'checkbox' ? (
				<input type="checkbox" disabled style={ { marginTop: '8px' } } />
			) : type === 'hidden' ? (
				<span style={ { color: '#646970', fontSize: '12px' } }>{ label }</span>
			) : (
				<input
					type={ previewInputType( type ) }
					readOnly
					placeholder={ placeholder }
					style={ inputStyle }
				/>
			) }
		</div>
	);
}

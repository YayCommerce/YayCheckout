import { RichTextToolbarButton } from '@wordpress/block-editor';
import { applyFormat, registerFormatType, removeFormat } from '@wordpress/rich-text';
import type { RichTextValue } from '@wordpress/rich-text';
import { __ } from '@wordpress/i18n';

/** Mirrors CheckoutLogin::TRIGGER_CLASS in PHP. */
export const TRIGGER_CLASS = 'yaycheckout-login__trigger';

export const TRIGGER_FORMAT = 'yaycheckout/login-trigger';

type FormatEditProps = {
	isActive: boolean;
	value: RichTextValue;
	onChange: ( value: RichTextValue ) => void;
};

function Edit( { isActive, value, onChange }: FormatEditProps ) {
	return (
		<RichTextToolbarButton
			name="link"
			icon="admin-links"
			title={ __( 'Login link', 'yaycheckout' ) }
			isActive={ isActive }
			onClick={ () => {
				if ( value.start === value.end ) {
					return;
				}

				if ( isActive ) {
					onChange( removeFormat( value, TRIGGER_FORMAT ) );
					return;
				}

				const cleared = removeFormat(
					{
						...value,
						start: 0,
						end: value.text.length,
					},
					TRIGGER_FORMAT
				);

				onChange(
					applyFormat(
						{
							...cleared,
							start: value.start,
							end: value.end,
						},
						{ type: TRIGGER_FORMAT }
					)
				);
			} }
		/>
	);
}

registerFormatType( TRIGGER_FORMAT, {
	title: __( 'Login link', 'yaycheckout' ),
	tagName: 'span',
	className: TRIGGER_CLASS,
	edit: Edit,
} );

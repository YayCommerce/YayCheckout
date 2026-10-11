import { RichText, useBlockProps } from '@wordpress/block-editor';

export interface StepNavButtonAttributes {
	buttonText: string;
}

export interface StepNavButtonEditProps {
	attributes: StepNavButtonAttributes;
	setAttributes: ( attributes: Partial< StepNavButtonAttributes > ) => void;
}

interface Props extends StepNavButtonEditProps {
	className: string;
	placeholder: string;
	/** An arrow glyph shown beside the label, as render.php prints it. Empty or missing shows none. */
	icon?: string;
	iconPosition?: 'before' | 'after';
	iconClassName?: string;
}

/** Continue, Back and Apply coupon. A div, not a button: typing Space inside a button would click it. */
export function StepNavButtonEdit( {
	className,
	placeholder,
	attributes,
	setAttributes,
	icon,
	iconPosition = 'after',
	iconClassName,
}: Props ) {
	const blockProps = useBlockProps( { className } );
	const richTextProps = {
		value: attributes.buttonText,
		onChange: ( value: string ) => setAttributes( { buttonText: value } ),
		placeholder,
		allowedFormats: [],
		withoutInteractiveFormatting: true,
	};

	if ( ! icon ) {
		return <RichText { ...blockProps } tagName="div" { ...richTextProps } />;
	}

	const iconSpan = (
		<span className={ iconClassName } aria-hidden="true">
			{ icon }
		</span>
	);

	return (
		<div { ...blockProps }>
			{ iconPosition === 'before' && iconSpan }
			<RichText tagName="span" { ...richTextProps } />
			{ iconPosition === 'after' && iconSpan }
		</div>
	);
}

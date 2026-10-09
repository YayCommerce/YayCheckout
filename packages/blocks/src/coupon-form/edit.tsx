import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, PanelColorSettings, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RevealLinkControls, RevealLinkPreview, defaultRevealText, isCollapsed } from '../shared/reveal-link';
import './editor.css';

/** The key and label render.php hands RevealLink for the coupon field. */
const COUPON_KEY = 'yaycheckout-coupon-code';
const COUPON_LABEL = __( 'Coupon code', 'yaycheckout' );

interface CouponFormAttributes {
	placeholder: string;
	buttonText: string;
	buttonBackgroundColor: string;
	buttonTextColor: string;
	collapseBehindLink: boolean;
	revealText: string;
}

interface EditProps {
	attributes: CouponFormAttributes;
	setAttributes: ( attributes: Partial< CouponFormAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	// The fallbacks mirror render.php, which owns the storefront defaults.
	const {
		placeholder,
		buttonText,
		buttonBackgroundColor = '',
		buttonTextColor = '',
		collapseBehindLink = false,
		revealText = '',
	} = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-coupon-form' } );
	const collapsed = isCollapsed( COUPON_KEY, false, collapseBehindLink );
	const buttonStyle = {
		...( buttonBackgroundColor ? { backgroundColor: buttonBackgroundColor } : {} ),
		...( buttonTextColor ? { color: buttonTextColor } : {} ),
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Coupon form', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Placeholder', 'yaycheckout' ) }
						value={ placeholder }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
					<TextControl
						label={ __( 'Button text', 'yaycheckout' ) }
						value={ decodeEntities( buttonText ) }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
					<RevealLinkControls
						fieldKey={ COUPON_KEY }
						label={ COUPON_LABEL }
						required={ false }
						collapseBehindLink={ collapseBehindLink }
						revealText={ revealText }
						setAttributes={ setAttributes }
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Button colors', 'yaycheckout' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							label: __( 'Background', 'yaycheckout' ),
							value: buttonBackgroundColor,
							onChange: ( value?: string ) => setAttributes( { buttonBackgroundColor: value ?? '' } ),
						},
						{
							label: __( 'Text', 'yaycheckout' ),
							value: buttonTextColor,
							onChange: ( value?: string ) => setAttributes( { buttonTextColor: value ?? '' } ),
						},
					] }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<RevealLinkPreview collapsed={ collapsed } text={ revealText || defaultRevealText( COUPON_KEY, COUPON_LABEL ) }>
					<div className="yaycheckout-coupon-form__row">
						<input
							type="text"
							disabled
							className="yaycheckout-coupon-form__code"
							placeholder={ placeholder || COUPON_LABEL }
						/>
						{ /* A div, not a button: typing Space inside a button would click it. The classes match render.php, so checkout-base.css draws it. */ }
						<RichText
							tagName="div"
							className="button wp-element-button yaycheckout-coupon-form__apply"
							style={ buttonStyle }
							value={ buttonText }
							onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
							placeholder={ __( 'Apply coupon', 'yaycheckout' ) }
							allowedFormats={ [] }
							withoutInteractiveFormatting
						/>
					</div>
				</RevealLinkPreview>
			</div>
		</>
	);
}

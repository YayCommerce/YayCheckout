import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { LIVE_VALUE_FORMAT } from '../live-value/constants';
import { HookOutputPreview } from '../shared/hook-output-preview';
import './editor.css';

interface PlaceOrderAttributes {
	buttonText?: string;
	spread: boolean;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
	setAttributes: ( attributes: Partial< PlaceOrderAttributes > ) => void;
}

/** The block editor's own classes, which stay on the block's root; the rest is the block's and goes on the button. */
const EDITOR_CLASS = /^(block-editor-|wp-block$|is-(?!style-)|has-child-selected$|has-warning$|has-editable-outline$)/;

/**
 * The block's root is a wrapper, as render.php's `.form-row.place-order` is, so what YayBoost or another plugin prints
 * before the button (HookOutputPreview) sits inside the block and its selection. The block's classes and style
 * supports go on the button, where render.php prints get_block_wrapper_attributes(); the wrapper keeps the editor's.
 * Mirrors the class and default label render.php prints on the <button>.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	// Core pads a label that ends in a live value with a U+FEFF text node, for the caret, and editor.css keeps it out
	// of the row's layout. The label holds no other format, so a closing </span> at the end is a live value.
	const endsWithValue = /<\/span>$/.test( attributes.buttonText ?? '' );
	const blockProps = useBlockProps( {
		className:
			'yaycheckout-place-order wp-element-button' +
			( attributes.spread ? ' yaycheckout-place-order--spread' : '' ) +
			( endsWithValue ? ' yaycheckout-place-order--ends-with-value' : '' ),
	} );
	const classes = ( blockProps.className ?? '' ).split( ' ' ).filter( Boolean );
	const wrapperClassName = classes.filter( ( name ) => EDITOR_CLASS.test( name ) ).join( ' ' );
	const buttonClassName = classes.filter( ( name ) => ! EDITOR_CLASS.test( name ) ).join( ' ' );
	const { style: buttonStyle, ...wrapperProps } = blockProps;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Spread', 'yaycheckout' ) }
						help={ __( 'Puts the label and the live values at opposite ends of the button.', 'yaycheckout' ) }
						checked={ attributes.spread }
						onChange={ ( spread: boolean ) => setAttributes( { spread } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...wrapperProps } className={ wrapperClassName }>
				{ /* Mirrors place-order/render.php's WcHooks::in_place() before the button. */ }
				<HookOutputPreview hook="woocommerce_review_order_before_submit" owner="block" />
				<RichText
					className={ buttonClassName }
					style={ buttonStyle }
					tagName="div"
					value={ attributes.buttonText ?? '' }
					onChange={ ( buttonText: string ) =>
						setAttributes( { buttonText: buttonText.replace( /\s*\n\s*/g, ' ' ) } )
					}
					placeholder={ __( 'Place order', 'yaycheckout' ) }
					// One line (pasted line breaks become spaces) whose only format is Live value: render.php fills it, then
					// copies the plain text into value and data-value.
					allowedFormats={ [ LIVE_VALUE_FORMAT ] }
					disableLineBreaks
				/>
			</div>
		</>
	);
}

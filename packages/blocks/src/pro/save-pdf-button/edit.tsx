import { InspectorControls, RichText, useBlockProps, Warning } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { editorData } from '../../shared/editor-data';

/** Mirrors OrderPrintSummary::SECTIONS. */
type Section = 'showLogo' | 'showOverview' | 'showPaymentInstructions' | 'showItems' | 'showAddresses' | 'showCustomerNote';

type SavePdfButtonAttributes = { buttonText: string } & Record< Section, boolean >;

interface EditProps {
	attributes: SavePdfButtonAttributes;
	setAttributes: ( attributes: Partial< SavePdfButtonAttributes > ) => void;
}

/** Mirrors OrderPrintSummary::SECTIONS and block.json's show* attributes (all on by default), in the order the summary prints them. */
const SECTIONS: Array< { name: Section; label: string; help?: string } > = [
	{ name: 'showLogo', label: __( 'Logo or site name', 'yaycheckout' ) },
	{
		name: 'showOverview',
		label: __( 'Order overview', 'yaycheckout' ),
		help: __( 'Order number, date, email, total and payment method.', 'yaycheckout' ),
	},
	{
		name: 'showPaymentInstructions',
		label: __( 'Payment instructions', 'yaycheckout' ),
		help: __( "What the payment method adds to the thank-you page, such as bank details.", 'yaycheckout' ),
	},
	{ name: 'showItems', label: __( 'Order details', 'yaycheckout' ), help: __( 'Items and totals.', 'yaycheckout' ) },
	{
		name: 'showAddresses',
		label: __( 'Addresses', 'yaycheckout' ),
		help: __( 'Left out for an account order unless its owner is signed in, as on the thank-you page.', 'yaycheckout' ),
	},
	{ name: 'showCustomerNote', label: __( 'Customer note', 'yaycheckout' ) },
];

/**
 * The button's label, edited in place: a div, not a <button>, so clicking edits the label.
 * Mirrors the class and default label save-pdf-button/render.php prints on the <button>.
 * The sidebar picks what the PDF holds; the page's other blocks are never in it.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const onThankYou = editorData.view.current === 'thank-you';
	const blockProps = useBlockProps( onThankYou ? { className: 'yaycheckout-save-pdf wp-element-button' } : {} );

	// Pasted into another view, the block prints nothing there (render.php), as Thank You Text warns.
	if ( ! onThankYou ) {
		return (
			<div { ...blockProps }>
				<Warning>
					{ __( 'Save as PDF Button shows only on the thank-you page. Move it to the Thank you view, or remove it.', 'yaycheckout' ) }
				</Warning>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'PDF content', 'yaycheckout' ) }>
					{ SECTIONS.map( ( { name, label, help } ) => (
						<ToggleControl
							key={ name }
							__nextHasNoMarginBottom
							label={ label }
							help={ help }
							checked={ attributes[ name ] !== false }
							onChange={ ( checked: boolean ) => setAttributes( { [ name ]: checked } ) }
						/>
					) ) }
				</PanelBody>
			</InspectorControls>
			<RichText
				{ ...blockProps }
				tagName="div"
				value={ attributes.buttonText }
				onChange={ ( buttonText: string ) => setAttributes( { buttonText } ) }
				placeholder={ __( 'Save as PDF', 'yaycheckout' ) }
				allowedFormats={ [] }
				withoutInteractiveFormatting
				disableLineBreaks
			/>
		</>
	);
}

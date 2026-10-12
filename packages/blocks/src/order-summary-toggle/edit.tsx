import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSummaryCollapse } from '../order-summary/shape';
import { Chevron } from '../shared/disclosure';
import './editor.css';

interface ToggleAttributes {
	showChevron: boolean;
}

interface EditProps {
	attributes: ToggleAttributes;
	setAttributes: ( attributes: Partial< ToggleAttributes > ) => void;
}

// No form controls or buttons, which a role="button" element can't hold; render.php prints any link as its text.
const ALLOWED_BLOCKS = [ 'core/paragraph', 'core/heading', 'core/image', 'core/group', 'core/spacer' ];

const TEMPLATE: [ string, Record< string, unknown > ][] = [ [ 'core/paragraph', { content: __( 'Order summary', 'yaycheckout' ) } ] ];

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { showChevron } = attributes;
	const { collapsible, open } = useSummaryCollapse();
	const { children, ...innerBlocksProps } = useInnerBlocksProps( useBlockProps( { className: 'yaycheckout-order-summary-toggle' } ), {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateLock: false,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Summary toggle', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show chevron', 'yaycheckout' ) }
						checked={ showChevron }
						onChange={ ( value ) => setAttributes( { showChevron: value } ) }
					/>
					<p>{ __( 'Opens and closes the Order Summary while it is collapsible.', 'yaycheckout' ) }</p>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps }>
				{ children }
				{ /* Same class render.php gives the storefront chevron. */ }
				{ collapsible && showChevron && (
					<Chevron className={ `yaycheckout-order-summary-toggle__chevron${ open ? ' is-open' : '' }` } />
				) }
			</div>
		</>
	);
}

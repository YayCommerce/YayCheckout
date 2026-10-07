import {
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { Notice, PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { isCheckoutSection, useBlocksAllowedExcept } from './allowed-blocks';
import { useContainerAppender } from './container-appender';
import { editorData } from './editor-data';

export interface AddressSectionAttributes {
	title: string;
	showTitle: boolean;
}

interface AddressSectionEditProps {
	section: 'billing' | 'shipping';
	clientId: string;
	attributes: AddressSectionAttributes;
	setAttributes: ( attributes: Partial< AddressSectionAttributes > ) => void;
}

export function AddressSectionEdit( {
	section,
	clientId,
	attributes,
	setAttributes,
}: AddressSectionEditProps ) {
	const { title, showTitle } = attributes;
	const fallbackTitle =
		section === 'shipping' ? __( 'Shipping', 'yaycheckout' ) : __( 'Billing', 'yaycheckout' );
	const blockProps = useBlockProps( { className: `yaycheckout-${ section }` } );
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	// WooCommerce-required billing fields the layout leaves out: checkout won't ask for them, so the merchant chooses.
	const missingLabels = useSelect(
		( select ) => {
			if ( section !== 'billing' ) {
				return '';
			}
			const { getClientIdsWithDescendants, getBlockName, getBlockAttributes } = select( blockEditorStore );
			// The Email address block is billing_email, as in LayoutMarkupReader::fields() (owner).
			const keys = getClientIdsWithDescendants().map( ( id: string ) =>
				getBlockName( id ) === 'yaycheckout/email' ? 'billing_email' : getBlockAttributes( id )?.fieldKey
			);

			return editorData.requiredByWooCommerce
				.filter( ( field ) => ! keys.includes( field.key ) )
				.map( ( field ) => field.label )
				.join( ', ' );
		},
		[ section ]
	);
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks,
		templateLock: false,
		renderAppender: useContainerAppender( clientId ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ fallbackTitle }>
					<ToggleControl
						label={ __( 'Show title', 'yaycheckout' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
					{ showTitle && (
						<TextControl
							label={ __( 'Title', 'yaycheckout' ) }
							value={ title }
							onChange={ ( value ) => setAttributes( { title: value } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps }>
				{ showTitle && <h3>{ title || fallbackTitle }</h3> }
				{ missingLabels !== '' && (
					<Notice status="warning" isDismissible={ false }>
						{ sprintf(
							/* translators: %s: comma-separated field labels. */
							__(
								"WooCommerce requires %s, but this layout leaves it out, so checkout won't ask for it.",
								'yaycheckout'
							),
							missingLabels
						) }
					</Notice>
				) }
				{ children }
			</div>
		</>
	);
}

import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import { type WcHookGroup, wcHooksEditorData } from './editor-data';
import './editor.css';

interface WcHookAttributes {
	hook: string;
}

interface EditProps {
	attributes: WcHookAttributes;
	setAttributes: ( attributes: Partial< WcHookAttributes > ) => void;
}

const GROUPS: WcHookGroup[] = [ 'page', 'customer', 'review', 'payment' ];

function groupLabel( group: WcHookGroup ): string {
	switch ( group ) {
		case 'page':
			return __( 'Page', 'yaycheckout' );
		case 'customer':
			return __( 'Customer details', 'yaycheckout' );
		case 'review':
			return __( 'Order review', 'yaycheckout' );
		case 'payment':
			return __( 'Payment', 'yaycheckout' );
	}
}

/** A placeholder: what plugins print depends on the store, and only shows on the checkout. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { hook } = attributes;
	const option = wcHooksEditorData.hooks.find( ( entry ) => entry.hook === hook );
	const blockProps = useBlockProps( { className: 'yaycheckout-wc-hook' } );

	// Every Hook block in the layout firing this hook, this one included.
	const placedCount = useSelect(
		( select ) => {
			if ( ! option ) {
				return 0;
			}

			const { getBlockName, getBlockAttributes, getClientIdsWithDescendants } = select( blockEditorStore );

			return ( getClientIdsWithDescendants() as string[] ).filter(
				( id ) => getBlockName( id ) === metadata.name && getBlockAttributes( id )?.hook === hook
			).length;
		},
		[ hook, option ]
	);

	const caption = option
		? sprintf(
				/* translators: %s: hook name, e.g. Before order notes */
				__( 'WooCommerce hook: %s', 'yaycheckout' ),
				option.label
		  )
		: __( 'WooCommerce hook: choose one in the block settings', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Hook', 'yaycheckout' ) }
						value={ option ? hook : '' }
						onChange={ ( next: string ) => setAttributes( { hook: next } ) }
						help={ option ? option.hook : undefined }
					>
						<option value="" disabled>
							{ __( 'Choose a hook', 'yaycheckout' ) }
						</option>
						{ GROUPS.map( ( group ) => (
							<optgroup key={ group } label={ groupLabel( group ) }>
								{ wcHooksEditorData.hooks
									.filter( ( entry ) => entry.group === group )
									.map( ( entry ) => (
										<option key={ entry.hook } value={ entry.hook }>
											{ entry.label }
										</option>
									) ) }
							</optgroup>
						) ) }
					</SelectControl>
					{ option && ! option.hasCallbacks && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Nothing on this store prints here yet, so shoppers see nothing.',
								'yaycheckout'
							) }
						</Notice>
					) }
					{ placedCount > 1 && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %d: how many WooCommerce Hook blocks fire this hook */
								_n(
									'This hook is placed %d time; it prints in each.',
									'This hook is placed %d times; it prints in each.',
									placedCount,
									'yaycheckout'
								),
								placedCount
							) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>{ caption }</div>
		</>
	);
}

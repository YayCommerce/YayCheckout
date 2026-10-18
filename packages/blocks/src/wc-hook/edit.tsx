import { InspectorControls, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import { wcHookIcon } from '../shared/block-icons';
import { type BlockEditorSelectors } from '../shared/section-fields';
import metadata from './block.json';
import { type WcHookGroup, wcHooksEditorData } from './editor-data';
import './editor.css';

interface WcHookAttributes {
	hook: string;
}

interface EditProps {
	attributes: WcHookAttributes;
	setAttributes: ( attributes: Partial< WcHookAttributes > ) => void;
	isSelected: boolean;
}

// In the order WcHooks::HOOKS lists them.
const GROUPS: WcHookGroup[] = [ ...new Set( wcHooksEditorData.hooks.map( ( entry ) => entry.group ) ) ];

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
export default function Edit( { attributes, setAttributes, isSelected }: EditProps ) {
	const { hook } = attributes;
	const option = wcHooksEditorData.hooks.find( ( entry ) => entry.hook === hook );
	const blockProps = useBlockProps( { className: 'yaycheckout-wc-hook' } );

	// Every Hook block in the layout firing this hook, this one included. The warning shows only in the
	// sidebar, so the count is taken only while the block is selected.
	const placedCount = useSelect(
		( select ) => {
			if ( ! option || ! isSelected ) {
				return 0;
			}

			const editor = select( blockEditorStore ) as unknown as BlockEditorSelectors;

			return editor
				.getBlocksByName( metadata.name )
				.filter( ( id ) => editor.getBlockAttributes( id )?.hook === hook ).length;
		},
		[ hook, option, isSelected ]
	);

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
								'Nothing hooks this in the editor. Some plugins add output only on the storefront, so preview to check.',
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
			<div { ...blockProps }>
				<span className="yaycheckout-wc-hook__icon">{ wcHookIcon }</span>
				<span className="yaycheckout-wc-hook__text">
					<span className="yaycheckout-wc-hook__eyebrow">{ __( 'WooCommerce hook', 'yaycheckout' ) }</span>
					<span className="yaycheckout-wc-hook__label">
						{ option ? option.label : __( 'Choose a hook in the block settings', 'yaycheckout' ) }
					</span>
				</span>
				{ option && <code className="yaycheckout-wc-hook__name">{ option.hook }</code> }
			</div>
		</>
	);
}

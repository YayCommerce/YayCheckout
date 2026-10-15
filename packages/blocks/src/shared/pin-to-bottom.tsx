import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import type { ComponentType } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useIsLayoutEditor } from './layout-editor';
import { addLayoutPanel, type LayoutPanelProps } from './layout-panel';
import './pin-to-bottom.css';

/** Mirrors PinToBottom::ATTRIBUTE in PHP. */
const ATTRIBUTE = 'yaycheckoutPinToBottom';

/** Mirrors PinToBottom::ALL in PHP. */
const ALL = 'all';

/** Mirrors PinToBottom::MOBILE in PHP. */
const MOBILE = 'mobile';

const GROUP = 'core/group';

/** Mirrors PinToBottom::filter_group() in PHP: any other value is off. */
const isPinned = ( value: unknown ): boolean => value === ALL || value === MOBILE;

/** The client ids of the pinned Groups, joined with spaces so the selector's result compares by value. */
function usePinnedGroupIds(): string[] {
	const ids = useSelect( ( select ) => {
		const { getBlockAttributes, getBlocksByName } = select( blockEditorStore );

		return ( getBlocksByName( GROUP ) as string[] )
			.filter( ( id ) => isPinned( getBlockAttributes( id )?.[ ATTRIBUTE ] ) )
			.join( ' ' );
	}, [] );

	return ids === '' ? [] : ids.split( ' ' );
}

function PinToBottomPanel( { clientId, attributes, setAttributes }: LayoutPanelProps ) {
	const value = typeof attributes[ ATTRIBUTE ] === 'string' ? ( attributes[ ATTRIBUTE ] as string ) : '';
	const otherPinned = usePinnedGroupIds().some( ( id ) => id !== clientId );

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Pin to bottom', 'yaycheckout' ) } initialOpen={ isPinned( value ) }>
				<SelectControl
					label={ __( 'Pin to the bottom of the screen', 'yaycheckout' ) }
					value={ value }
					options={ [
						{ label: __( 'Off', 'yaycheckout' ), value: '' },
						{ label: __( 'All screens', 'yaycheckout' ), value: ALL },
						// The breakpoint matches checkout-base.css's @media (max-width: 781px).
						{ label: __( 'Mobile only (below 782px)', 'yaycheckout' ), value: MOBILE },
					] }
					onChange={ ( next: string ) => setAttributes( { [ ATTRIBUTE ]: next } ) }
					help={ __(
						'On the checkout this block stays at the bottom of the screen. The editor shows it in place, marked with a pin.',
						'yaycheckout'
					) }
				/>
				{ value === ALL && ( attributes.style as { position?: { type?: string } } | undefined )?.position?.type === 'sticky' && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Pinned to the bottom, this block ignores its sticky position.', 'yaycheckout' ) }
					</Notice>
				) }
				{ isPinned( value ) && otherPinned && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Another block is already pinned to the bottom. They will overlap.', 'yaycheckout' ) }
					</Notice>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

addLayoutPanel( 'yaycheckout/pin-to-bottom', [ GROUP ], ATTRIBUTE, { type: 'string', default: '' }, PinToBottomPanel );

/**
 * Nothing on a List View row reflects a block's attributes, so this style names the pinned Groups' rows
 * by client id: it turns on the pin pin-to-bottom.css draws, and hides core's sticky pin, which a pinned
 * Group ignores, so a row never shows two.
 */
function PinnedListViewStyle() {
	const ids = usePinnedGroupIds();

	if ( ids.length === 0 ) {
		return null;
	}

	const rows = `.block-editor-list-view-leaf:is(${ ids.map( ( id ) => `[data-block="${ id }"]` ).join( ',' ) })`;

	return (
		<style>
			{ `${ rows } { --yaycheckout-list-view-pin: ""; } ${ rows } .block-editor-list-view-block-select-button__sticky { display: none; }` }
		</style>
	);
}

registerPlugin( 'yaycheckout-pin-to-bottom-marker', {
	render: function PinToBottomMarker() {
		return useIsLayoutEditor() ? <PinnedListViewStyle /> : null;
	},
} );

type ListBlockProps = {
	name: string;
	attributes: Record< string, unknown >;
	className?: string;
	wrapperProps?: Record< string, unknown >;
};

type PinnedBlockProps = ListBlockProps & { BlockListBlock: ComponentType< ListBlockProps > };

/** A pinned Group on the canvas: the pin badge, whose hover tag reads the translated data attribute. */
function PinnedBlock( { BlockListBlock, ...props }: PinnedBlockProps ) {
	if ( ! useIsLayoutEditor() ) {
		return <BlockListBlock { ...props } />;
	}

	const label =
		props.attributes[ ATTRIBUTE ] === MOBILE
			? __( 'Pinned to bottom on mobile', 'yaycheckout' )
			: __( 'Pinned to bottom', 'yaycheckout' );

	return (
		<BlockListBlock
			{ ...props }
			className={ [ props.className, 'is-yaycheckout-pinned' ].filter( Boolean ).join( ' ' ) }
			wrapperProps={ { ...props.wrapperProps, 'data-yaycheckout-pinned': label } }
		/>
	);
}

const withPinnedMarker = createHigherOrderComponent(
	( BlockListBlock: ComponentType< ListBlockProps > ) =>
		function PinToBottomBlockListBlock( props: ListBlockProps ) {
			return props.name === GROUP && isPinned( props.attributes?.[ ATTRIBUTE ] ) ? (
				<PinnedBlock { ...props } BlockListBlock={ BlockListBlock } />
			) : (
				<BlockListBlock { ...props } />
			);
		},
	'withPinnedMarker'
);

addFilter( 'editor.BlockListBlock', 'yaycheckout/pin-to-bottom/marker', withPinnedMarker );

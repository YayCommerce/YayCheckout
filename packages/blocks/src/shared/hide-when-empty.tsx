import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { getBlockType, hasBlockSupport } from '@wordpress/blocks';
import { CheckboxControl, Notice, PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { editorData } from './editor-data';

/** Mirrors HideWhenEmpty::ATTRIBUTE in PHP. */
const ATTRIBUTE = 'yaycheckoutHideWhenEmpty';

/** Mirrors HideWhenEmpty::SUPPORT in PHP. */
const SUPPORT = 'yaycheckoutWatchable';

const GROUP = 'core/group';

const REQUIRED_FIELD_BLOCKS = [ 'yaycheckout/field', 'yaycheckout/email' ];

/** Mirrors HideWhenEmpty::BLOCK_ID_PATTERN in PHP: 8 characters from [a-z0-9]. */
function newBlockId(): string {
	const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	const bytes = crypto.getRandomValues( new Uint8Array( 8 ) );

	return Array.from( bytes, ( byte ) => chars[ byte % chars.length ] ).join( '' );
}

type Watchable = { clientId: string; blockId: string; label: string };

type BlockSettings = { name?: string; attributes?: Record< string, unknown > };

addFilter(
	'blocks.registerBlockType',
	'yaycheckout/hide-when-empty/attribute',
	( settings: BlockSettings, name: string ) =>
		name === GROUP
			? {
					...settings,
					attributes: {
						...settings.attributes,
						[ ATTRIBUTE ]: { type: 'array', items: { type: 'string' }, default: [] },
					},
			  }
			: settings
);

type PanelProps = {
	clientId: string;
	attributes: Record< string, unknown >;
	setAttributes: ( attributes: Record< string, unknown > ) => void;
};

function HideWhenEmptyPanel( { clientId, attributes, setAttributes }: PanelProps ) {
	const watched = Array.isArray( attributes[ ATTRIBUTE ] ) ? ( attributes[ ATTRIBUTE ] as string[] ) : [];

	const { watchable, copies, hasRequiredField } = useSelect(
		( select ) => {
			const { getBlockName, getBlockAttributes, getClientIdsOfDescendants, getClientIdsWithDescendants } =
				select( blockEditorStore );
			const descendants: string[] = getClientIdsOfDescendants( [ clientId ] );
			const firstWithId: Record< string, string > = {};
			const copyIds: string[] = [];

			// A copied block keeps its original's blockId; every block after the first holding it is a copy.
			getClientIdsWithDescendants().forEach( ( id: string ) => {
				const blockId = getBlockAttributes( id )?.blockId;

				if ( typeof blockId !== 'string' || blockId === '' ) {
					return;
				}

				if ( firstWithId[ blockId ] ) {
					copyIds.push( id );
				} else {
					firstWithId[ blockId ] = id;
				}
			} );

			const list: Watchable[] = descendants
				.filter( ( id ) => hasBlockSupport( getBlockName( id ), SUPPORT ) )
				.map( ( id ) => {
					const name = getBlockName( id );
					const attrs = getBlockAttributes( id ) ?? {};
					const title = getBlockType( name )?.title ?? name;

					return {
						clientId: id,
						blockId: typeof attrs.blockId === 'string' ? attrs.blockId : '',
						label: attrs.slotName ? `${ title } · ${ attrs.slotName }` : title,
					};
				} );

			return {
				watchable: list,
				copies: list.filter( ( block ) => copyIds.includes( block.clientId ) ).map( ( block ) => block.clientId ),
				hasRequiredField: descendants.some(
					( id ) =>
						REQUIRED_FIELD_BLOCKS.includes( getBlockName( id ) ) &&
						getBlockAttributes( id )?.required === true
				),
			};
		},
		[ clientId ]
	);
	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	const present = watchable.map( ( block ) => block.blockId ).filter( ( id ) => id !== '' );
	const live = watched.filter( ( id ) => present.includes( id ) );

	// A watched block moved out of the Group or deleted: drop its id, as the server ignores it.
	useEffect( () => {
		if ( live.length !== watched.length ) {
			setAttributes( { [ ATTRIBUTE ]: live } );
		}
	}, [ live.join( ' ' ), watched.join( ' ' ) ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// The server can't tell a copy from its original, so a copy gets its own id, left unticked.
	useEffect( () => {
		copies.forEach( ( id ) => updateBlockAttributes( id, { blockId: newBlockId() } ) );
	}, [ copies.join( ' ' ) ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const toggle = ( block: Watchable, checked: boolean ) => {
		if ( ! checked ) {
			setAttributes( { [ ATTRIBUTE ]: live.filter( ( id ) => id !== block.blockId ) } );
			return;
		}

		let blockId = block.blockId;

		if ( blockId === '' ) {
			blockId = newBlockId();
			updateBlockAttributes( block.clientId, { blockId } );
		}

		setAttributes( { [ ATTRIBUTE ]: [ ...live, blockId ] } );
	};

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Hide when empty', 'yaycheckout' ) } initialOpen={ live.length > 0 }>
				{ watchable.length === 0 ? (
					<p>
						{ __(
							'Add a Shipping methods, Payment, Express pay, Slot, Coupon form, Login or Create account block to this group.',
							'yaycheckout'
						) }
					</p>
				) : (
					<>
						<p>{ __( 'Hide this group while every ticked block is empty.', 'yaycheckout' ) }</p>
						{ watchable.map( ( block ) => (
							<CheckboxControl
								key={ block.clientId }
								label={ block.label }
								checked={ block.blockId !== '' && live.includes( block.blockId ) }
								onChange={ ( checked: boolean ) => toggle( block, checked ) }
							/>
						) ) }
					</>
				) }
				{ live.length > 0 && hasRequiredField && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							"This group contains required fields. If it hides, shoppers can't complete checkout.",
							'yaycheckout'
						) }
					</Notice>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

const withHideWhenEmptyPanel = createHigherOrderComponent(
	( BlockEdit ) =>
		function HideWhenEmptyBlockEdit( props: PanelProps & { name: string; isSelected: boolean } ) {
			const isLayout = useSelect(
				( select ) => select( editorStore ).getCurrentPostType() === editorData.postType,
				[]
			);

			return (
				<>
					<BlockEdit { ...props } />
					{ props.name === GROUP && props.isSelected && isLayout && <HideWhenEmptyPanel { ...props } /> }
				</>
			);
		},
	'withHideWhenEmptyPanel'
);

addFilter( 'editor.BlockEdit', 'yaycheckout/hide-when-empty/panel', withHideWhenEmptyPanel );

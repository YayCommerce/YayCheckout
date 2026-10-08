import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { getBlockType, getBlockTypes, hasBlockSupport } from '@wordpress/blocks';
import { CheckboxControl, Notice, PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { select as selectNow, useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { editorData } from './editor-data';

/** Mirrors HideWhenEmpty::ATTRIBUTE in PHP. */
const ATTRIBUTE = 'yaycheckoutHideWhenEmpty';

/** Mirrors HideWhenEmpty::SUPPORT in PHP. */
const SUPPORT = 'yaycheckoutWatchable';

const GROUP = 'core/group';

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

	const { watchable, renames, sharedIds, hasRequiredField } = useSelect(
		( select ) => {
			const { getBlockName, getBlockAttributes, getClientIdsOfDescendants, getClientIdsWithDescendants } =
				select( blockEditorStore );
			const descendants: string[] = getClientIdsOfDescendants( [ clientId ] );
			const inGroup = new Set( descendants );
			const groupWatches = getBlockAttributes( clientId )?.[ ATTRIBUTE ];
			const holders: Record< string, string[] > = {};

			getClientIdsWithDescendants().forEach( ( id: string ) => {
				const blockId = getBlockAttributes( id )?.blockId;

				if ( typeof blockId === 'string' && blockId !== '' ) {
					( holders[ blockId ] = holders[ blockId ] ?? [] ).push( id );
				}
			} );

			// A copied block keeps its original's blockId, and the server can't tell them apart. When this
			// Group watches the id, its first holder here keeps it and every other holder is renamed;
			// otherwise only the holders here are, so another Group's watched block is left alone.
			const toRename: string[] = [];

			Object.entries( holders ).forEach( ( [ blockId, ids ] ) => {
				const here = ids.filter( ( id ) => inGroup.has( id ) );

				if ( ids.length < 2 || here.length === 0 ) {
					return;
				}

				const keeper =
					Array.isArray( groupWatches ) && groupWatches.includes( blockId ) ? here[ 0 ] : undefined;

				toRename.push( ...( keeper ? ids.filter( ( id ) => id !== keeper ) : here ) );
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
				renames: toRename,
				sharedIds: Object.keys( holders ).filter( ( blockId ) => holders[ blockId ].length > 1 ),
				// Any block whose block.json declares `required`: Field, Email and Extra field today.
				hasRequiredField: descendants.some(
					( id ) =>
						!! getBlockType( getBlockName( id ) )?.attributes?.required &&
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

	// A renamed block's other Groups follow it, unless they still hold another block with the old id.
	useEffect( () => {
		const { getBlockAttributes, getBlockName, getBlockParents, getClientIdsOfDescendants } =
			selectNow( blockEditorStore );

		renames.forEach( ( id ) => {
			const oldId = getBlockAttributes( id )?.blockId;
			const blockId = newBlockId();

			updateBlockAttributes( id, { blockId } );

			getBlockParents( id ).forEach( ( parent: string ) => {
				const parentWatches = getBlockAttributes( parent )?.[ ATTRIBUTE ];
				const stillHeld = getClientIdsOfDescendants( [ parent ] ).some(
					( other: string ) => getBlockAttributes( other )?.blockId === oldId
				);

				if (
					getBlockName( parent ) === GROUP &&
					Array.isArray( parentWatches ) &&
					parentWatches.includes( oldId ) &&
					! stillHeld
				) {
					updateBlockAttributes( parent, {
						[ ATTRIBUTE ]: parentWatches.map( ( watchedId: string ) =>
							watchedId === oldId ? blockId : watchedId
						),
					} );
				}
			} );
		} );
	}, [ renames.join( ' ' ) ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const toggle = ( block: Watchable, checked: boolean ) => {
		if ( ! checked ) {
			setAttributes( { [ ATTRIBUTE ]: live.filter( ( id ) => id !== block.blockId ) } );
			return;
		}

		let blockId = block.blockId;

		if ( blockId === '' || sharedIds.includes( blockId ) ) {
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
						{ sprintf(
							/* translators: %s: comma-separated block titles. */
							__( 'Add one of these blocks to this group: %s.', 'yaycheckout' ),
							getBlockTypes()
								.filter( ( type ) => hasBlockSupport( type, SUPPORT ) )
								.map( ( type ) => type.title )
								.join( ', ' )
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

/** Subscribes to the store only while a Group is selected, so other blocks pay nothing. */
function LayoutOnlyPanel( props: PanelProps ) {
	const isLayout = useSelect( ( select ) => select( editorStore ).getCurrentPostType() === editorData.postType, [] );

	return isLayout ? <HideWhenEmptyPanel { ...props } /> : null;
}

const withHideWhenEmptyPanel = createHigherOrderComponent(
	( BlockEdit ) =>
		function HideWhenEmptyBlockEdit( props: PanelProps & { name: string; isSelected: boolean } ) {
			return (
				<>
					<BlockEdit { ...props } />
					{ props.name === GROUP && props.isSelected && <LayoutOnlyPanel { ...props } /> }
				</>
			);
		},
	'withHideWhenEmptyPanel'
);

addFilter( 'editor.BlockEdit', 'yaycheckout/hide-when-empty/panel', withHideWhenEmptyPanel );

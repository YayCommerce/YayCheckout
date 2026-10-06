import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useRef } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';
import checkoutMeta from './block.json';

const CHECKOUT_BLOCK = checkoutMeta.name;

/**
 * With no block selected, core's header inserter targets the post root, where
 * no YayCheckout block may go, so this points it at the end of Checkout. Once a
 * block is selected it hands back to core, which inserts after the selection or
 * into its closest ancestor that allows the block.
 */
function LayoutInserterRoot() {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );
	const isInserterOpened = useSelect( ( select ) => select( editorStore ).isInserterOpened(), [] );
	const destination = useSelect( ( select ) => {
		if ( postType !== editorData.postType ) {
			return null;
		}

		const { getBlockCount, getBlockName, getClientIdsWithDescendants, getSelectedBlockClientId } =
			select( blockEditorStore );

		if ( getSelectedBlockClientId() ) {
			return null;
		}

		const rootClientId = getClientIdsWithDescendants().find(
			( id: string ) => getBlockName( id ) === CHECKOUT_BLOCK
		);

		return rootClientId ? { rootClientId, insertionIndex: getBlockCount( rootClientId ) } : null;
	}, [ postType ] );
	const { setIsInserterOpened } = useDispatch( editorStore );
	const lastKey = useRef( '' );

	useEffect( () => {
		if ( ! isInserterOpened ) {
			lastKey.current = '';
			return;
		}

		if ( ! destination ) {
			// The editor keeps an explicit root ahead of the selection, so drop ours.
			if ( lastKey.current !== '' ) {
				lastKey.current = '';
				setIsInserterOpened( true );
			}
			return;
		}

		const key = destination.rootClientId + ':' + String( destination.insertionIndex );

		if ( lastKey.current === key ) {
			return;
		}

		lastKey.current = key;
		setIsInserterOpened( destination );
	}, [ isInserterOpened, destination, setIsInserterOpened ] );

	return null;
}

registerPlugin( 'yaycheckout-layout-inserter-root', {
	render: LayoutInserterRoot,
} );

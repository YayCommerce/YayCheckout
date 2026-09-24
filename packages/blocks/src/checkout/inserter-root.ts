import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useRef } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';

const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const ADDRESS_SECTIONS = [ 'yaycheckout/billing', 'yaycheckout/shipping' ];

/**
 * Header inserter follows the selection: Billing/Shipping when those
 * canvases are selected (so Field appears), otherwise the Checkout block,
 * wherever it sits in the document.
 * Contact and the document never list Field.
 */
function LayoutInserterRoot() {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );
	const isInserterOpened = useSelect( ( select ) => select( editorStore ).isInserterOpened(), [] );
	const destination = useSelect( ( select ) => {
		if ( postType !== editorData.postType ) {
			return null;
		}

		const { getBlockCount, getBlockName, getClientIdsWithDescendants, getSelectedBlock } =
			select( blockEditorStore );
		const rootClientId = getClientIdsWithDescendants().find(
			( id: string ) => getBlockName( id ) === CHECKOUT_BLOCK
		);

		if ( ! rootClientId ) {
			return null;
		}

		const selected = getSelectedBlock();

		if ( selected && ADDRESS_SECTIONS.includes( selected.name ) ) {
			return {
				rootClientId: selected.clientId,
				insertionIndex: getBlockCount( selected.clientId ),
			};
		}

		return {
			rootClientId,
			insertionIndex: getBlockCount( rootClientId ),
		};
	}, [ postType ] );
	const { setIsInserterOpened } = useDispatch( editorStore );
	const lastKey = useRef( '' );

	useEffect( () => {
		if ( ! isInserterOpened || ! destination ) {
			lastKey.current = '';
			return;
		}

		const key = destination.rootClientId + ':' + String( destination.insertionIndex );

		if ( lastKey.current === key ) {
			return;
		}

		lastKey.current = key;
		setIsInserterOpened( {
			rootClientId: destination.rootClientId,
			insertionIndex: destination.insertionIndex,
		} );
	}, [ isInserterOpened, destination, setIsInserterOpened ] );

	return null;
}

registerPlugin( 'yaycheckout-layout-inserter-root', {
	render: LayoutInserterRoot,
} );

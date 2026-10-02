import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useRef } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';

const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const STEPS = 'yaycheckout/steps';
const STEP = 'yaycheckout/step';
const ADDRESS_SECTIONS = [ 'yaycheckout/billing', 'yaycheckout/shipping' ];

/**
 * Header inserter follows the selection: Billing/Shipping so Field appears,
 * a Step so sections land in that step, Steps so another
 * step can be added, otherwise the Checkout block.
 * Contact and the document never list Field.
 */

function stepsInsertion(
	clientId: string,
	getBlockName: ( id: string ) => string | undefined,
	getBlockParents: ( id: string ) => string[],
	getBlockCount: ( id: string ) => number
): { rootClientId: string; insertionIndex: number } | null {
	const chain = [ clientId, ...getBlockParents( clientId ) ];
	const stepId = chain.find( ( id ) => getBlockName( id ) === STEP );
	const containerId = stepId ?? chain.find( ( id ) => getBlockName( id ) === STEPS );

	if ( ! containerId ) {
		return null;
	}

	return {
		rootClientId: containerId,
		insertionIndex: getBlockCount( containerId ),
	};
}

function LayoutInserterRoot() {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );
	const isInserterOpened = useSelect( ( select ) => select( editorStore ).isInserterOpened(), [] );
	const destination = useSelect( ( select ) => {
		if ( postType !== editorData.postType ) {
			return null;
		}

		const { getBlockCount, getBlockName, getBlockParents, getClientIdsWithDescendants, getSelectedBlock } =
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

		if ( selected ) {
			const nested = stepsInsertion( selected.clientId, getBlockName, getBlockParents, getBlockCount );

			if ( nested ) {
				return nested;
			}
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

import { createHigherOrderComponent } from '@wordpress/compose';
import type { ComponentType } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { editorData } from '../shared/editor-data';
import { useScopeClass } from '../shared/checkout-scope';

type ListBlockProps = {
	rootClientId?: string;
	className?: string;
};

/**
 * In the Thank you view, the canvas's top-level blocks: the scope class, which the thank-you template puts
 * around the page on the storefront (thankyou-page.php), so the checkout's styles reach them in the editor
 * too. The thank-you page has no Checkout block to carry them. On the block's editor wrapper only, never saved.
 */
function ScopedBlock( { BlockListBlock, ...props }: ListBlockProps & { BlockListBlock: ComponentType< ListBlockProps > } ) {
	const scope = useScopeClass();

	return <BlockListBlock { ...props } className={ [ props.className, scope ].filter( Boolean ).join( ' ' ) } />;
}

const withThankYouScope = createHigherOrderComponent(
	( BlockListBlock: ComponentType< ListBlockProps > ) =>
		function ThankYouScopeBlockListBlock( props: ListBlockProps ) {
			return props.rootClientId ? (
				<BlockListBlock { ...props } />
			) : (
				<ScopedBlock { ...props } BlockListBlock={ BlockListBlock } />
			);
		},
	'withThankYouScope'
);

// The view is fixed per page load: LayoutEditorEmbed serves one view's markup.
if ( editorData.view.current === 'thank-you' ) {
	addFilter( 'editor.BlockListBlock', 'yaycheckout/thank-you-scope', withThankYouScope );
}

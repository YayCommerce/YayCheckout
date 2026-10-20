import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { editorData } from './editor-data';

/** Whether the post in the editor is a layout, not a page or post that the blocks may also load in. */
export function useIsLayoutEditor(): boolean {
	return useSelect( ( select ) => select( editorStore ).getCurrentPostType() === editorData.postType, [] );
}

/**
 * Whether the post editor is showing a revision. Core's isRevisionsMode() is private; in the post editor
 * only revisions mode sets the block editor's isPreviewMode.
 */
export function useIsPreviewingRevision(): boolean {
	return useSelect(
		( select ) => !! ( select( blockEditorStore ).getSettings() as { isPreviewMode?: boolean } ).isPreviewMode,
		[]
	);
}

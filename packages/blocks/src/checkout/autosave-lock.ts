import { useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { useIsLayoutEditor } from '../shared/layout-editor';

const AUTOSAVE_LOCK = 'yaycheckout-layout-autosave';

/**
 * A layout saves only through the header's Save and Activate: core's autosave
 * writes a draft's edits straight into the post. The lock stops both the server
 * autosave and the browser backup, and leaves explicit saves alone.
 */
function LayoutAutosaveLock() {
	const isLayout = useIsLayoutEditor();
	const { lockPostAutosaving, unlockPostAutosaving } = useDispatch( editorStore );

	useEffect( () => {
		if ( ! isLayout ) {
			return;
		}

		lockPostAutosaving( AUTOSAVE_LOCK );

		return () => unlockPostAutosaving( AUTOSAVE_LOCK );
	}, [ isLayout, lockPostAutosaving, unlockPostAutosaving ] );

	return null;
}

registerPlugin( 'yaycheckout-layout-autosave-lock', {
	render: LayoutAutosaveLock,
} );

import { select, useDispatch, useSelect } from '@wordpress/data';
import { PluginMoreMenuItem } from '@wordpress/editor';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useIsLayoutEditor } from '../../shared/layout-editor';
import Tour from './tour';
import './editor-tour.css';

/**
 * Whether this user has closed the tour: a `core/preferences` value, which WordPress keeps per user in the
 * `persisted_preferences` user meta, so the tour starts once per user and not again on another browser.
 */
const PREFERENCE_SCOPE = 'yaycheckout';
const PREFERENCE_DONE = 'editorTourDone';

/**
 * A first-run tour of the layout editor: where the canvas, the inserter, Layout design and the header buttons
 * are, with one guided design change on the way. It starts by itself whenever a user opens a layout until they
 * skip, close or finish it, and the ⋮ menu's Editor tour plays it again.
 */
function EditorTour() {
	const isLayout = useIsLayoutEditor();
	const isDone = useSelect(
		( selectStore ) => true === selectStore( 'core/preferences' ).get( PREFERENCE_SCOPE, PREFERENCE_DONE ),
		[]
	);
	const { set } = useDispatch( 'core/preferences' );
	const [ isOpen, setOpen ] = useState( false );
	const started = useRef( false );

	useEffect( () => {
		if ( ! isLayout ) {
			return;
		}

		// Core's own welcome guide would open over the tour, and explains the post editor, not a layout.
		if ( select( 'core/preferences' ).get( 'core/edit-post', 'welcomeGuide' ) ) {
			set( 'core/edit-post', 'welcomeGuide', false );
		}

		if ( ! isDone && ! started.current ) {
			started.current = true;
			setOpen( true );
		}
	}, [ isLayout ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! isLayout ) {
		return null;
	}

	const close = () => {
		setOpen( false );
		set( PREFERENCE_SCOPE, PREFERENCE_DONE, true );
	};

	return (
		<>
			{
				// The editor package has it from WordPress 6.6; on 6.5 the tour still starts by itself.
				PluginMoreMenuItem && (
					<PluginMoreMenuItem icon="lightbulb" onClick={ () => setOpen( true ) }>
						{ __( 'Editor tour', 'yaycheckout' ) }
					</PluginMoreMenuItem>
				)
			}
			{ isOpen && <Tour onClose={ close } /> }
		</>
	);
}

registerPlugin( 'yaycheckout-editor-tour', {
	render: EditorTour,
} );

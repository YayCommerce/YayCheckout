import { store as blockEditorStore } from '@wordpress/block-editor';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { PluginMoreMenuItem } from '@wordpress/editor';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useIsLayoutEditor } from '../../shared/layout-editor';
import { FEATURE_TOURS, type FeatureTour } from './feature-tours';
import { tourSteps } from './steps';
import Tour from './tour';
import './editor-tour.css';

/**
 * Whether this user has closed the tour: a `core/preferences` value, which WordPress keeps per user in the
 * `persisted_preferences` user meta, so the tour starts once per user and not again on another browser.
 */
const PREFERENCE_SCOPE = 'yaycheckout';
const PREFERENCE_DONE = 'editorTourDone';

/** The ids of the feature tours this user has closed, kept the same way. */
const PREFERENCE_FEATURES_SEEN = 'featureToursSeen';

/**
 * Plays a feature's tour the first time the user selects a block that has the feature, never over the editor
 * tour. It reacts to a change of selection, so a block the editor tour selected doesn't start one as it closes.
 */
function FeatureTours( { isPaused }: { isPaused: boolean } ) {
	const seen = useSelect( ( selectStore ) => {
		const value = selectStore( 'core/preferences' ).get( PREFERENCE_SCOPE, PREFERENCE_FEATURES_SEEN );
		return Array.isArray( value ) ? ( value as string[] ) : [];
	}, [] );
	const { clientId, blockName } = useSelect( ( selectStore ) => {
		const blocks = selectStore( blockEditorStore );
		const selected = blocks.getSelectedBlockClientId();

		return { clientId: selected, blockName: selected ? blocks.getBlockName( selected ) : null };
	}, [] );
	const { set } = useDispatch( 'core/preferences' );
	const [ active, setActive ] = useState< FeatureTour | null >( null );
	const steps = useMemo( () => active?.steps() ?? [], [ active ] );

	useEffect( () => {
		if ( isPaused || active || ! blockName ) {
			return;
		}

		const next = FEATURE_TOURS.find( ( tour ) => ! seen.includes( tour.id ) && tour.blocks.includes( blockName ) );

		if ( next ) {
			setActive( next );
		}
	}, [ clientId ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! active ) {
		return null;
	}

	const close = () => {
		set( PREFERENCE_SCOPE, PREFERENCE_FEATURES_SEEN, [ ...seen, active.id ] );
		setActive( null );
	};

	return <Tour steps={ steps } onClose={ close } startLabel={ __( 'Show me', 'yaycheckout' ) } />;
}

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
	const steps = useMemo( tourSteps, [] );

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
			{ isOpen && <Tour steps={ steps } onClose={ close } /> }
			<FeatureTours isPaused={ isOpen } />
		</>
	);
}

registerPlugin( 'yaycheckout-editor-tour', {
	render: EditorTour,
} );

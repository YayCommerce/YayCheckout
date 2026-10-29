import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink, Notice } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { pluginBlocksData, pluginBlocksDataFrom, type PluginBlockEntry } from './plugin-blocks-data';
import { pluginBlocksStore } from './store';
import { editorData } from '../shared/editor-data';
import { errorMessage } from '../shared/error-message';
import { loadEditorStyles } from '../shared/load-editor-styles';
import './editor.css';

interface PluginBlockCardProps {
	target: string;
}

/** A link styled as the card's action button. */
function LinkAction( { href, children }: { href: string; children: string } ) {
	return (
		<Button variant="secondary" href={ href } target="_blank" rel="noreferrer">
			{ children }
		</Button>
	);
}

/** What the user does about the plugin: `onClick` in place when they may, or else who can. */
function PluginAction( props: { allowed: boolean; onClick: () => void; busy: boolean; label: string; denied: string } ) {
	const { allowed, onClick, busy, label, denied } = props;

	return allowed ? (
		<Button variant="primary" onClick={ onClick } isBusy={ busy } disabled={ busy }>
			{ label }
		</Button>
	) : (
		<span className="yaycheckout-plugin-block__note">{ denied }</span>
	);
}

/**
 * The card for a spot another Yay plugin's feature fills (PluginBlockCatalogue): which feature, and what to do until
 * the plugin's shortcode prints it.
 */
export function PluginBlockCard( { target }: PluginBlockCardProps ) {
	const entry: PluginBlockEntry | undefined = pluginBlocksData.entries.find( ( item ) => item.target === target );
	const pluginState = useSelect( ( select ) => select( pluginBlocksStore ).getPluginState( target ), [ target ] );
	const { setPluginState } = useDispatch( pluginBlocksStore );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	if ( ! entry || ! pluginState ) {
		return <p className="yaycheckout-plugin-block__pitch">{ __( 'This block is no longer offered.', 'yaycheckout' ) }</p>;
	}

	const { state, pluginPath } = pluginState;

	/**
	 * Loads what a fresh editor would print, its styles included, then moves every spot of this plugin to the state
	 * that page reports, so each of its cards follows. Without the page's catalogue, the states stay as they were.
	 */
	const refresh = async () => {
		const page = await loadEditorStyles( editorData.view.urls[ editorData.view.current ] || window.location.href ).catch( () => null );
		const fresh = page ? pluginBlocksDataFrom( page ) : null;

		for ( const item of pluginBlocksData.entries ) {
			const reported = fresh?.entries.find( ( next ) => next.target === item.target );

			if ( item.plugin === entry.plugin && item.available && reported ) {
				setPluginState( item.target, reported.state, reported.pluginPath );
			}
		}
	};

	const run = async ( request: () => Promise< unknown > ) => {
		setBusy( true );
		setError( '' );

		try {
			await request();
			await refresh();
		} catch ( failure ) {
			setError( await errorMessage( failure, __( 'Something went wrong. Try again from the Plugins screen.', 'yaycheckout' ) ) );
		}

		setBusy( false );
	};

	// Core's /wp/v2/plugins: installs from wordpress.org and activates in one call.
	const install = () =>
		run( () =>
			apiFetch( {
				path: '/wp/v2/plugins',
				method: 'POST',
				data: { slug: entry.wporgSlug, status: 'active' },
			} )
		);

	const activate = () =>
		run( () =>
			apiFetch( {
				path: '/wp/v2/plugins/' + String( pluginPath ).replace( /\.php$/, '' ),
				method: 'POST',
				data: { status: 'active' },
			} )
		);

	const checkAgain = () => run( async () => null );

	const name = entry.pluginName;

	const checkAgainButton = (
		<Button variant="tertiary" onClick={ checkAgain } isBusy={ busy } disabled={ busy }>
			{ __( 'Check again', 'yaycheckout' ) }
		</Button>
	);

	let action: JSX.Element | null = null;

	switch ( state ) {
		case 'not_installed':
			action = (
				<PluginAction
					allowed={ pluginBlocksData.canInstall }
					onClick={ install }
					busy={ busy }
					label={ sprintf( /* translators: %s: plugin name. */ __( 'Install & activate %s', 'yaycheckout' ), name ) }
					denied={ sprintf( /* translators: %s: plugin name. */ __( 'Ask a site administrator to install %s.', 'yaycheckout' ), name ) }
				/>
			);
			break;
		case 'inactive':
			action = (
				<PluginAction
					allowed={ pluginBlocksData.canActivate }
					onClick={ activate }
					busy={ busy }
					label={ sprintf( /* translators: %s: plugin name. */ __( 'Activate %s', 'yaycheckout' ), name ) }
					denied={ sprintf( /* translators: %s: plugin name. */ __( 'Ask a site administrator to activate %s.', 'yaycheckout' ), name ) }
				/>
			);
			break;
		case 'unlicensed':
			action = (
				<>
					<LinkAction href={ entry.licenseUrl }>
						{ sprintf( /* translators: %s: plugin name. */ __( 'Activate your %s license', 'yaycheckout' ), name ) }
					</LinkAction>
					{ checkAgainButton }
				</>
			);
			break;
		case 'feature_off':
			action = (
				<>
					{ entry.settingsUrl !== '' && (
						<LinkAction href={ entry.settingsUrl }>
							{ sprintf( /* translators: %s: plugin name. */ __( 'Turn on in %s', 'yaycheckout' ), name ) }
						</LinkAction>
					) }
					{ checkAgainButton }
				</>
			);
			break;
		case 'shortcode':
			action = (
				<>
					<span className="yaycheckout-plugin-block__note">
						{ sprintf( /* translators: 1: block title, 2: plugin name. */ __( '%1$s from %2$s shows here.', 'yaycheckout' ), entry.title, name ) }
					</span>
					{ entry.settingsUrl !== '' && (
						<ExternalLink href={ entry.settingsUrl }>{ sprintf( /* translators: %s: plugin name. */ __( 'Manage in %s', 'yaycheckout' ), name ) }</ExternalLink>
					) }
				</>
			);
			break;
		case 'unavailable':
			action = (
				<span className="yaycheckout-plugin-block__soon">
					{ sprintf( /* translators: %s: plugin name. */ __( 'Coming soon to %s', 'yaycheckout' ), name ) }
				</span>
			);
			break;
	}

	// The Plugins screen is only worth a link to a user who may act there.
	const fallbackAllowed = state === 'not_installed' ? pluginBlocksData.canInstall : pluginBlocksData.canActivate;

	return (
		<>
			<div className="yaycheckout-plugin-block__head">
				{ entry.iconUrl && <img className="yaycheckout-plugin-block__icon" src={ entry.iconUrl } alt="" /> }
				<span className="yaycheckout-plugin-block__tag">{ name }</span>
				<strong className="yaycheckout-plugin-block__title">{ entry.title }</strong>
			</div>
			<p className="yaycheckout-plugin-block__pitch">{ entry.pitch }</p>
			{ error !== '' && (
				<Notice className="yaycheckout-plugin-block__error" status="error" isDismissible={ false }>
					{ error }
					{ fallbackAllowed && (
						<>
							{ ' ' }
							<ExternalLink href={ state === 'not_installed' ? entry.installUrl : entry.pluginsUrl }>
								{ state === 'not_installed' ? __( 'Install it from the Plugins screen', 'yaycheckout' ) : __( 'Open the Plugins screen', 'yaycheckout' ) }
							</ExternalLink>
						</>
					) }
				</Notice>
			) }
			<div className="yaycheckout-plugin-block__actions">
				{ action }
				<ExternalLink href={ entry.learnMoreUrl }>{ __( 'Learn more', 'yaycheckout' ) }</ExternalLink>
			</div>
		</>
	);
}

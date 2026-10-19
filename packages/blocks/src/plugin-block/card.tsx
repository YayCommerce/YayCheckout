import apiFetch from '@wordpress/api-fetch';
import { getBlockType } from '@wordpress/blocks';
import { Button, ExternalLink, Notice } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { pluginBlocksData, pluginBlocksDataFrom, type PluginBlockEntry, type PluginBlockState } from './plugin-blocks-data';
import { pluginBlocksStore } from './store';
import { editorData } from '../shared/editor-data';
import { errorMessage } from '../shared/error-message';
import { loadEditorAssets } from '../shared/load-editor-assets';
import './editor.css';

interface PluginBlockCardProps {
	target: string;
	/** Swaps in the real block; shown once it is registered. */
	addBlock: () => void;
	/** Swaps in the placeholder, which prints the shortcode; only where the spot isn't one already. */
	useFree?: () => void;
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
 * The card for a spot that another Yay plugin's block fits (PluginBlockCatalogue): which block, what to do to get
 * it, and Add block once its plugin has registered it. A free edition's shortcode fills the spot meanwhile. The
 * placeholder block and a missing real block both render it.
 */
export function PluginBlockCard( { target, addBlock, useFree }: PluginBlockCardProps ) {
	const entry: PluginBlockEntry | undefined = pluginBlocksData.entries.find( ( item ) => item.target === target );
	const pluginState = useSelect( ( select ) => select( pluginBlocksStore ).getPluginState( target ), [ target ] );
	const { setPluginState } = useDispatch( pluginBlocksStore );
	const { savePost } = useDispatch( editorStore );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	// Set once the server says ready but loading the editor's new assets still left the block unregistered.
	const [ needsReload, setNeedsReload ] = useState( false );

	if ( ! entry || ! pluginState ) {
		return <p className="yaycheckout-plugin-block__pitch">{ __( 'This block is no longer offered.', 'yaycheckout' ) }</p>;
	}

	const { state, pluginPath } = pluginState;

	/**
	 * Loads what a fresh editor would print, then moves every block of this plugin to the state that page reports,
	 * so each of its cards follows. A block the server calls ready that still didn't register needs Save and reload.
	 * Without the page's catalogue, the registered block is all there is to go by.
	 */
	const refresh = async ( path: string | null ) => {
		const page = await loadEditorAssets( editorData.view.urls[ editorData.view.current ] || window.location.href ).catch( () => null );
		const fresh = page ? pluginBlocksDataFrom( page ) : null;
		let reload = false;

		for ( const item of pluginBlocksData.entries ) {
			if ( item.plugin !== entry.plugin || ! item.available ) {
				continue;
			}

			const reported = fresh?.entries.find( ( next ) => next.target === item.target );
			const registered = Boolean( getBlockType( item.target ) );
			let next: PluginBlockState = reported ? reported.state : 'ready';

			if ( next === 'ready' && ! registered ) {
				next = 'feature_off';
				reload = reload || item.target === target;
			}

			setPluginState( item.target, next, reported ? reported.pluginPath : path );
		}

		setNeedsReload( reload );
	};

	const run = async ( request: () => Promise< string | null > ) => {
		setBusy( true );
		setError( '' );

		try {
			await refresh( await request() );
		} catch ( failure ) {
			setError( await errorMessage( failure, __( 'Something went wrong. Try again from the Plugins screen.', 'yaycheckout' ) ) );
		}

		setBusy( false );
	};

	// Core's /wp/v2/plugins: installs from wordpress.org and activates in one call. Its `plugin` drops the .php.
	const install = () =>
		run( async () => {
			const result = await apiFetch< { plugin: string } >( {
				path: '/wp/v2/plugins',
				method: 'POST',
				data: { slug: entry.wporgSlug, status: 'active' },
			} );

			return result.plugin + '.php';
		} );

	const activate = () =>
		run( async () => {
			await apiFetch( {
				path: '/wp/v2/plugins/' + String( pluginPath ).replace( /\.php$/, '' ),
				method: 'POST',
				data: { status: 'active' },
			} );

			return pluginPath;
		} );

	const checkAgain = () => run( async () => pluginPath );

	const saveAndReload = async () => {
		setBusy( true );
		await savePost();
		window.location.reload();
	};

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
				<>
					<PluginAction
						allowed={ pluginBlocksData.canInstall }
						onClick={ install }
						busy={ busy }
						label={ sprintf( /* translators: %s: plugin name. */ __( 'Install & activate %s', 'yaycheckout' ), name ) }
						denied={ sprintf( /* translators: %s: plugin name. */ __( 'Ask a site administrator to install %s.', 'yaycheckout' ), name ) }
					/>
					{ entry.upgradeUrl !== '' && (
						<ExternalLink href={ entry.upgradeUrl }>
							{ sprintf( /* translators: %s: plugin name. */ __( '%s Pro adds it as a block', 'yaycheckout' ), name ) }
						</ExternalLink>
					) }
				</>
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
					{ needsReload && (
						<Button variant="tertiary" onClick={ saveAndReload } disabled={ busy }>
							{ __( 'Save and reload', 'yaycheckout' ) }
						</Button>
					) }
				</>
			);
			break;
		case 'shortcode':
			action = (
				<>
					<span className="yaycheckout-plugin-block__note">
						{ sprintf( /* translators: 1: block title, 2: plugin name. */ __( '%1$s from %2$s shows here.', 'yaycheckout' ), entry.title, name ) }
					</span>
					{ useFree && (
						<Button variant="primary" onClick={ useFree }>
							{ sprintf( /* translators: %s: plugin name. */ __( 'Use free %s here', 'yaycheckout' ), name ) }
						</Button>
					) }
					{ entry.settingsUrl !== '' && (
						<ExternalLink href={ entry.settingsUrl }>{ sprintf( /* translators: %s: plugin name. */ __( 'Manage in %s', 'yaycheckout' ), name ) }</ExternalLink>
					) }
					{ entry.upgradeUrl !== '' && (
						<ExternalLink href={ entry.upgradeUrl }>
							{ sprintf( /* translators: %s: plugin name. */ __( 'Upgrade to %s Pro to place it as a block', 'yaycheckout' ), name ) }
						</ExternalLink>
					) }
				</>
			);
			break;
		case 'ready':
			action = (
				<Button variant="primary" onClick={ addBlock }>
					{ __( 'Add block', 'yaycheckout' ) }
				</Button>
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

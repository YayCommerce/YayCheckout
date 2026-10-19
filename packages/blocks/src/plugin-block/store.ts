import { createReduxStore, register } from '@wordpress/data';
import { pluginBlocksData, type PluginBlockState } from './plugin-blocks-data';

type PluginState = { state: PluginBlockState; pluginPath: string | null };
/** Per target, so a plugin's blocks can differ (one feature on, another off). */
type State = Record< string, PluginState >;
type Action = { type: 'SET_PLUGIN_STATE'; target: string; state: PluginBlockState; pluginPath: string | null };

const initial: State = Object.fromEntries(
	pluginBlocksData.entries.map( ( entry ) => [ entry.target, { state: entry.state, pluginPath: entry.pluginPath } ] )
);

/**
 * Where each promoted block stands, seeded from the page's catalogue and updated after an install or activate,
 * so every placeholder for the same plugin follows one click.
 */
export const pluginBlocksStore = createReduxStore( 'yaycheckout/plugin-blocks', {
	reducer: ( state: State = initial, action: Action ): State =>
		action.type === 'SET_PLUGIN_STATE'
			? { ...state, [ action.target ]: { state: action.state, pluginPath: action.pluginPath } }
			: state,
	actions: {
		setPluginState: ( target: string, state: PluginBlockState, pluginPath: string | null ): Action => ( {
			type: 'SET_PLUGIN_STATE',
			target,
			state,
			pluginPath,
		} ),
	},
	selectors: {
		getPluginState: ( state: State, target: string ): PluginState | undefined => state[ target ],
	},
} );

register( pluginBlocksStore );

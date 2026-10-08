import { createReduxStore, register } from '@wordpress/data';

/** Whether the open layout is in the active set; null until layout-actions.tsx has fetched it. */
type State = { isActive: boolean | null };
type Action = { type: 'SET_ACTIVE'; isActive: boolean | null };

/**
 * The one place the editor keeps a layout's active state: layout-actions.tsx writes it,
 * and publish-guard.ts reads it, so an active layout stays locked even if the editor's
 * own status is stale.
 */
export const layoutStateStore = createReduxStore( 'yaycheckout/layout-state', {
	reducer: ( state: State = { isActive: null }, action: Action ): State =>
		action.type === 'SET_ACTIVE' ? { isActive: action.isActive } : state,
	actions: {
		setActive: ( isActive: boolean | null ): Action => ( { type: 'SET_ACTIVE', isActive } ),
	},
	selectors: {
		isActive: ( state: State ) => state.isActive,
	},
} );

register( layoutStateStore );

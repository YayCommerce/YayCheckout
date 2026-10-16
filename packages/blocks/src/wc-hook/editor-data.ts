/** Mirrors the group values of WcHooks::HOOKS in PHP. */
export type WcHookGroup = 'page' | 'customer' | 'review' | 'payment';

export type WcHookOption = { hook: string; label: string; group: WcHookGroup; hasCallbacks: boolean };

type WcHooksEditorData = { hooks: WcHookOption[] };

declare global {
	interface Window {
		yayCheckoutWcHooks?: WcHooksEditorData;
	}
}

/**
 * Values BlockRegistry prints before this bundle runs: WcHooks::HOOKS with WcHooks::labels() and
 * WcHooks::has_callbacks() in PHP, in their order.
 */
export const wcHooksEditorData: WcHooksEditorData = window.yayCheckoutWcHooks ?? { hooks: [] };

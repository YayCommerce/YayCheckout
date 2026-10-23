/**
 * Messages between the layout manager (the SPA's Edit layout dialog) and the embedded editor in its iframe.
 * The editor says which view is open; the manager's page boxes ask it to switch. Both sides are on the
 * wp-admin origin, so each checks the message's origin and sender before acting on it.
 */

/** Matches EditorView[ 'current' ] in editor-data.ts, which the SPA can't import: it reads the editor's globals. */
export type LayoutView = 'main' | 'logged-out' | 'thank-you';

/** Editor → manager, on each editor load: the open view, and whether the layout has a thank-you page yet. */
export type EditorViewMessage = {
	source: 'yaycheckout-editor';
	type: 'view';
	current: LayoutView;
	hasThankYou: boolean;
};

/** Manager → editor: open this view, asking to save first when there are unsaved edits. */
export type SwitchViewMessage = {
	source: 'yaycheckout-manager';
	type: 'switch-view';
	view: LayoutView;
};

export function isEditorViewMessage( data: unknown ): data is EditorViewMessage {
	const message = data as Partial< EditorViewMessage > | null;

	return message?.source === 'yaycheckout-editor' && message.type === 'view';
}

export function isSwitchViewMessage( data: unknown ): data is SwitchViewMessage {
	const message = data as Partial< SwitchViewMessage > | null;

	return message?.source === 'yaycheckout-manager' && message.type === 'switch-view';
}

import { __ } from '@wordpress/i18n';

/**
 * Visible label for a step title.
 *
 * An empty attribute stays empty in the saved block. Owner: Renderer\LayoutSteps::step_title().
 */
export function stepTitle( title: string ): string {
	return title !== '' ? title : __( 'Step', 'yaycheckout' );
}

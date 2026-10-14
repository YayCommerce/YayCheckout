import { PanelBody } from '@wordpress/components';
import type { ReactNode } from '@wordpress/element';
import './tools-panel.css';

/**
 * A Styles tab ToolsPanel inside a collapsible panel, closed by default, so a block's own style
 * panels fold away. The ToolsPanel keeps its label for its menu, but tools-panel.css hides it, since
 * this panel's title already shows it.
 */
export function StyleSection( { title, children }: { title: string; children: ReactNode } ) {
	return (
		<PanelBody title={ title } initialOpen={ false } className="yaycheckout-style-section">
			{ children }
		</PanelBody>
	);
}

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { EditorView } from '../shared/editor-data';
import './layout-pages.css';

type View = EditorView[ 'current' ];

/** Per viewer, so the column stays as the merchant left it across view switches, each its own page load. */
const COLLAPSED_KEY = 'yaycheckout-layout-pages-collapsed';

const svg = ( children: JSX.Element ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="20"
		height="20"
		fill="none"
		stroke="currentColor"
		strokeWidth={ 1.5 }
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		focusable="false"
	>
		{ children }
	</svg>
);

const cartIcon = svg(
	<>
		<path d="M3 4.5h2.5l2 10h11l1.75-7H6" />
		<circle cx="9" cy="18.5" r="1.25" />
		<circle cx="17" cy="18.5" r="1.25" />
	</>
);
const thankYouIcon = svg(
	<>
		<circle cx="12" cy="12" r="8.5" />
		<path d="m8.5 12.25 2.5 2.25 4.5-4.5" />
	</>
);
const addIcon = svg( <path d="M12 6v12M6 12h12" /> );
const collapseIcon = svg( <path d="m11 8-4 4 4 4M17 8l-4 4 4 4" /> );
const expandIcon = svg( <path d="m7 8 4 4-4 4M13 8l4 4-4 4" /> );

function readCollapsed(): boolean {
	try {
		return window.localStorage.getItem( COLLAPSED_KEY ) === '1';
	} catch {
		return false;
	}
}

function writeCollapsed( collapsed: boolean ) {
	try {
		window.localStorage.setItem( COLLAPSED_KEY, collapsed ? '1' : '0' );
	} catch {
		// Storage blocked: the column still toggles, it just isn't remembered.
	}
}

type Props = {
	current: View;
	hasThankYou: boolean;
	disabled: boolean;
	onPick: ( view: View ) => void;
};

/**
 * The layout's pages, in a column left of the editor's header and body: Checkout and Thank you, or Add
 * thank-you page while the layout has none. Guest is a variant of the checkout, so it keeps Checkout
 * selected. A pick goes through LayoutViewSwitch's pick(), so the save-first prompt applies.
 */
export default function LayoutPages( { current, hasThankYou, disabled, onPick }: Props ) {
	const [ collapsed, setCollapsed ] = useState( readCollapsed );
	const active: View = current === 'thank-you' ? 'thank-you' : 'main';

	const toggle = () => {
		writeCollapsed( ! collapsed );
		setCollapsed( ! collapsed );
	};

	const item = ( view: View, label: string, icon: JSX.Element, extraClass = '' ) => {
		const isActive = view === active && ! extraClass;

		return (
			<button
				type="button"
				className={ `yaycheckout-layout-pages__item${ isActive ? ' is-active' : '' }${ extraClass }` }
				aria-current={ isActive ? 'page' : undefined }
				aria-label={ collapsed ? label : undefined }
				title={ collapsed ? label : undefined }
				disabled={ disabled }
				onClick={ () => ! isActive && onPick( view ) }
			>
				{ icon }
				<span className="yaycheckout-layout-pages__label">{ label }</span>
			</button>
		);
	};

	return (
		<nav
			className={ `yaycheckout-layout-pages__nav${ collapsed ? ' is-collapsed' : '' }` }
			aria-label={ __( 'Layout pages', 'yaycheckout' ) }
		>
			{ item( 'main', __( 'Checkout', 'yaycheckout' ), cartIcon ) }
			{ hasThankYou || current === 'thank-you'
				? item( 'thank-you', __( 'Thank you', 'yaycheckout' ), thankYouIcon )
				: item( 'thank-you', __( 'Add thank-you page', 'yaycheckout' ), addIcon, ' is-add' ) }
			<button
				type="button"
				className="yaycheckout-layout-pages__toggle"
				aria-expanded={ ! collapsed }
				aria-label={ collapsed ? __( 'Expand', 'yaycheckout' ) : undefined }
				title={ collapsed ? __( 'Expand', 'yaycheckout' ) : undefined }
				onClick={ toggle }
			>
				{ collapsed ? expandIcon : collapseIcon }
				<span className="yaycheckout-layout-pages__label">{ __( 'Collapse', 'yaycheckout' ) }</span>
			</button>
		</nav>
	);
}

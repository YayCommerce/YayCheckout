import { store as blockEditorStore } from '@wordpress/block-editor';
import { dispatch, select as selectStore, type select as selectFn } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { editorData } from '../../shared/editor-data';
import { SIDEBAR } from '../layout-design';

/** Where the card sits against its target: below it, lined up with its left or right edge, or to its left (its right without room). */
export type Placement = 'bottom-start' | 'bottom-end' | 'left-start';

export type TourStep = {
	id: string;
	title: string;
	content: string;
	/**
	 * In place of `content` when no target shows: the card is centred, so this says where the area is or how to
	 * reach it. Required with a target; a step without one is always a centred card.
	 */
	fallback?: string;
	/** Selectors for the area the step lights up, tried in order. None, or no match, lights nothing. */
	target?: string[];
	/** Selectors for blocks in the canvas, tried before `target`. */
	canvasTarget?: string[];
	/** Beside the target; without one, or when the target is too big to sit beside, the card is centred. */
	placement?: Placement;
	/** Runs as the step opens, e.g. to open the sidebar it points at. */
	open?: () => void;
	/**
	 * A guided step: the merchant does something instead of pressing Next. Once this value differs from its
	 * value when the step opened, Next shows, and the tour moves on by itself when no picker is left open.
	 */
	watch?: ( select: typeof selectFn ) => unknown;
};

/** Core's editor areas: the 6.6+ class first, then the 6.5 one. */
const CANVAS = [ '.editor-visual-editor', '.edit-post-visual-editor', 'iframe[name="editor-canvas"]' ];
const INSERTER = [ '.editor-document-tools__inserter-toggle', '.edit-post-header-toolbar__inserter-toggle' ];

/** The Place order button: the primary colour fills it, so it shows the merchant's first change. */
const PLACE_ORDER = '[data-type="yaycheckout/place-order"]';

/** Scrolls the canvas to its first match of the selector. */
function scrollCanvasTo( selector: string ) {
	document
		.querySelector< HTMLIFrameElement >( 'iframe[name="editor-canvas"]' )
		?.contentDocument?.querySelector( selector )
		?.scrollIntoView( { block: 'center', behavior: 'smooth' } );
}

/** The block settings sidebar and its Styles tab: core's tab ids end in the tab's name, its label is translated. */
const BLOCK_INSPECTOR = '.block-editor-block-inspector';
const STYLES_TAB = `${ BLOCK_INSPECTOR } [role="tab"][id$="-styles"]`;
const STYLES_PANEL = `${ BLOCK_INSPECTOR } [role="tabpanel"][id$="-styles-view"]`;

/** How many frames a step waits for a control that its sidebar renders after opening. */
const READY_FRAMES = 60;

/** Clicks the selector's first match once it renders, unless it is already the selected tab. */
function clickWhenReady( selector: string, frames = READY_FRAMES ) {
	const element = document.querySelector< HTMLElement >( selector );

	if ( element ) {
		if ( 'true' !== element.getAttribute( 'aria-selected' ) ) {
			element.click();
		}
	} else if ( frames > 0 ) {
		window.requestAnimationFrame( () => clickWhenReady( selector, frames - 1 ) );
	}
}

/** The first Place order block's client id, if the layout has one. */
function placeOrderBlock(): string | undefined {
	const blocks = selectStore( blockEditorStore );

	return blocks
		.getClientIdsWithDescendants()
		.find( ( clientId: string ) => 'yaycheckout/place-order' === blocks.getBlockName( clientId ) );
}

/** Selects the Place order block, if the layout has one, and shows its Styles tab in the block settings sidebar. */
function openBlockStyles() {
	const clientId = placeOrderBlock();

	if ( ! clientId ) {
		return;
	}

	dispatch( blockEditorStore ).selectBlock( clientId );
	dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
	clickWhenReady( STYLES_TAB );
}

/** The ComplementaryArea id core gives a PluginSidebar: `<plugin>/<sidebar>`, both SIDEBAR here. */
const DESIGN_AREA = `${ SIDEBAR }/${ SIDEBAR }`;

/** The steps, in order. Without the design data the Layout design sidebar isn't there, so its steps are left out. */
export function tourSteps(): TourStep[] {
	const design = editorData.design;

	const steps: Array< TourStep | false > = [
		{
			id: 'welcome',
			title: __( 'Welcome to your checkout editor', 'yaycheckout' ),
			content: __(
				"Your checkout is built from blocks. This one-minute tour shows you around, then you'll make your first design change.",
				'yaycheckout'
			),
		},
		{
			id: 'canvas',
			title: __( 'This is your checkout', 'yaycheckout' ),
			content: __(
				'Click any block to edit its text, fields and settings. Drag blocks to reorder them.',
				'yaycheckout'
			),
			fallback: __(
				'Your checkout is built from blocks. To see and edit them, open the ⋮ menu at the top right and choose Visual editor.',
				'yaycheckout'
			),
			target: CANVAS,
		},
		{
			id: 'inserter',
			title: __( 'Add blocks', 'yaycheckout' ),
			content: __(
				'Add fields, notices, the order summary and more. Only blocks that work on a checkout are listed.',
				'yaycheckout'
			),
			fallback: __(
				'The + button at the top left of the editor adds fields, notices, the order summary and more. Only blocks that work on a checkout are listed.',
				'yaycheckout'
			),
			target: INSERTER,
			placement: 'bottom-start',
		},
		!! design && {
			id: 'design',
			title: __( 'Layout design', 'yaycheckout' ),
			content: __(
				"Colours, fonts, inputs and checkboxes for this layout live here. Let's open it.",
				'yaycheckout'
			),
			fallback: __(
				"Colours, fonts, inputs and checkboxes for this layout live in Layout design, in the ⋮ menu at the top right. Let's open it.",
				'yaycheckout'
			),
			// The toggle core pins to the header, unless the user unpinned it; its aria-controls is the area id with ':' for '/'.
			target: [ `button[aria-controls="${ SIDEBAR }:${ SIDEBAR }"]` ],
			placement: 'bottom-end',
		},
		!! design && {
			id: 'colour',
			title: __( 'Pick your brand colour', 'yaycheckout' ),
			content: __(
				'Click Primary and choose a colour. It colours your buttons and the selected options. Click outside the picker when you like it.',
				'yaycheckout'
			),
			fallback: __(
				'In Layout design, click Primary under Colours and choose a colour. It colours your buttons and the selected options.',
				'yaycheckout'
			),
			// ColourSettings tags each panel with its panelId.
			target: [ `.${ SIDEBAR }-colours` ],
			placement: 'left-start',
			open: () => dispatch( 'core/interface' ).enableComplementaryArea( 'core', DESIGN_AREA ),
			watch: ( select ) =>
				(
					select( editorStore ).getEditedPostAttribute( 'meta' ) as
						| Record< string, { colours?: { primary?: unknown } } >
						| undefined
				)?.[ design.metaKey ]?.colours?.primary,
		},
		!! design && {
			id: 'live',
			title: __( 'See it live', 'yaycheckout' ),
			content: __(
				'Your colour is already on Place order, and on the selected shipping and payment options. Layout design styles the whole layout; leave a value empty to follow your theme.',
				'yaycheckout'
			),
			fallback: __(
				'Your colour is already on the canvas, wherever this layout uses its primary colour. Layout design styles the whole layout; leave a value empty to follow your theme.',
				'yaycheckout'
			),
			canvasTarget: [ PLACE_ORDER ],
			placement: 'left-start',
			open: () => scrollCanvasTo( PLACE_ORDER ),
		},
		{
			id: 'block-styles',
			title: __( 'Style one block', 'yaycheckout' ),
			content: __(
				'Need one block to look different? Its Styles tab overrides Layout design for that block only. Clear a value to follow the layout again.',
				'yaycheckout'
			),
			fallback: __(
				'Need one block to look different? Select it, then open the Styles tab in its block settings: what you set there overrides Layout design for that block only.',
				'yaycheckout'
			),
			target: [ STYLES_PANEL ],
			placement: 'left-start',
			open: openBlockStyles,
		},
		{
			id: 'actions',
			title: __( 'Preview, save and go live', 'yaycheckout' ),
			content: __(
				'Preview opens your real checkout with this layout. Save keeps your changes. Activate makes the layout the checkout your shoppers see.',
				'yaycheckout'
			),
			fallback: __(
				'Preview, Save and Activate are at the top right of the editor. Preview opens your real checkout with this layout, Save keeps your changes, and Activate makes the layout the checkout your shoppers see.',
				'yaycheckout'
			),
			target: [ '.yaycheckout-layout-actions' ],
			placement: 'bottom-end',
		},
		{
			id: 'done',
			title: __( "You're all set", 'yaycheckout' ),
			content: __( 'To see this tour again, open the ⋮ menu at the top right and choose Editor tour.', 'yaycheckout' ),
		},
	];

	return steps.filter( ( step ): step is TourStep => !! step );
}

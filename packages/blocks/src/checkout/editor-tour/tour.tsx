import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createPortal, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { type Placement, type TourStep } from './steps';
import { useTargetRect } from './use-target';

/** Space between the target and the edge of its lit-up area. */
const SPOTLIGHT_PADDING = 6;

/** The card's width, as `.yaycheckout-tour__card` sets it, and its distance from the target and the window edge. */
const CARD_WIDTH = 340;
const CARD_GAP = 12;
const EDGE = 16;

/** A target wider than this share of the window gets the centred card: there is no room beside it. */
const MAX_ANCHOR_WIDTH = 0.6;

/** How long a guided step lets the merchant see their change before moving on. */
const GUIDED_DELAY = 500;

/**
 * How long a step waits for its target before it gives up and shows its fallback, centred: a sidebar the step
 * opens, or a block the canvas scrolls to, renders a moment after the step starts.
 */
const TARGET_WAIT = 800;

/** A picker the merchant still has open: a guided step waits for it to close. */
const OPEN_PICKER = '.components-dropdown__content';

/** Dims the editor except the target. It never takes a click, so the lit-up area stays usable. */
function Spotlight( { rect }: { rect: DOMRect | null } ) {
	const style = rect
		? {
				top: rect.top - SPOTLIGHT_PADDING,
				left: rect.left - SPOTLIGHT_PADDING,
				width: rect.width + SPOTLIGHT_PADDING * 2,
				height: rect.height + SPOTLIGHT_PADDING * 2,
		  }
		: { top: '50%', left: '50%', width: 0, height: 0 };

	return createPortal( <div className="yaycheckout-tour__spotlight" style={ style } aria-hidden />, document.body );
}

/** The card's top left beside the target, kept inside the window. */
function cardPosition( rect: DOMRect, placement: Placement ): { top: number; left: number } {
	const width = Math.min( CARD_WIDTH, window.innerWidth - EDGE * 2 );
	let top = rect.bottom + CARD_GAP;
	let left = rect.left;

	if ( 'bottom-end' === placement ) {
		left = rect.right - width;
	} else if ( 'left-start' === placement ) {
		top = rect.top;
		left = rect.left - CARD_GAP - width;
		// No room on the left: the right side instead.
		if ( left < EDGE ) {
			left = rect.right + CARD_GAP;
		}
	}

	return {
		top: Math.max( EDGE, top ),
		left: Math.min( Math.max( EDGE, left ), window.innerWidth - width - EDGE ),
	};
}

/**
 * Whether the guided step's watched value has moved off the value it had when the step opened. Once it has,
 * onDone runs as soon as no picker is open, so the tour doesn't move on under a picker the merchant is still using.
 */
function useGuided( watch: TourStep[ 'watch' ], onDone: () => void ): boolean {
	const value = useSelect( ( select ) => watch?.( select ), [ watch ] );
	const start = useRef( value );
	const isChanged = !! watch && value !== start.current;

	useEffect( () => {
		if ( ! isChanged ) {
			return;
		}

		let timer = 0;
		const poll = window.setInterval( () => {
			if ( ! timer && ! document.querySelector( OPEN_PICKER ) ) {
				timer = window.setTimeout( onDone, GUIDED_DELAY );
			}
		}, 200 );

		return () => {
			window.clearInterval( poll );
			window.clearTimeout( timer );
		};
	}, [ isChanged ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return isChanged;
}

/** One step's card. Keyed by the step, so a guided step's starting value is taken when it opens. */
function Step( {
	step,
	index,
	steps,
	go,
	onClose,
	onRect,
	startLabel,
}: {
	step: TourStep;
	index: number;
	steps: TourStep[];
	go: ( next: number ) => void;
	onClose: () => void;
	startLabel: string;
	/** Hands the target's place to the spotlight, which outlives the step so it can glide to the next one. */
	onRect: ( rect: DOMRect | null ) => void;
} ) {
	const rect = useTargetRect( step.target, step.canvasTarget );
	const primary = useRef< HTMLButtonElement >( null );
	const isFirst = 0 === index;
	const isLast = steps.length - 1 === index;
	const isChanged = useGuided( step.watch, () => go( index + 1 ) );
	const hasTarget = !! ( step.target?.length || step.canvasTarget?.length );
	const [ isLate, setLate ] = useState( false );
	const isMissing = hasTarget && ! rect;
	// Until the target shows or the wait is over, no card: one shown early would jump, or change its text.
	const isShown = ! isMissing || isLate;

	useEffect( () => onRect( rect ), [ rect ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		if ( ! hasTarget ) {
			return;
		}

		const timer = window.setTimeout( () => setLate( true ), TARGET_WAIT );
		return () => window.clearTimeout( timer );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Each step's card takes focus once it shows, so the keyboard follows the tour.
	useEffect( () => {
		if ( isShown ) {
			primary.current?.focus();
		}
	}, [ isShown ] );

	if ( ! isShown ) {
		return null;
	}

	const anchored =
		step.placement && rect && rect.width < window.innerWidth * MAX_ANCHOR_WIDTH
			? cardPosition( rect, step.placement )
			: null;

	const card = (
		<div
			className="yaycheckout-tour__card"
			role="dialog"
			aria-labelledby="yaycheckout-tour-title"
			onKeyDown={ ( event ) => {
				if ( 'Escape' === event.key ) {
					event.stopPropagation();
					onClose();
				}
			} }
		>
			<div className="yaycheckout-tour__head">
				<span className="yaycheckout-tour__count">
					{
						/* translators: 1: this step's number, 2: the number of steps */
						sprintf( __( '%1$d of %2$d', 'yaycheckout' ), index + 1, steps.length )
					}
				</span>
				<Button size="small" icon="no-alt" label={ __( 'Close tour', 'yaycheckout' ) } onClick={ onClose } />
			</div>
			<h2 id="yaycheckout-tour-title" className="yaycheckout-tour__title">
				{ step.title }
			</h2>
			<p className="yaycheckout-tour__content">{ isMissing ? step.fallback ?? step.content : step.content }</p>
			<div className="yaycheckout-tour__foot">
				<div className="yaycheckout-tour__dots" aria-hidden>
					{ steps.map( ( { id }, i ) => (
						<span key={ id } className={ `yaycheckout-tour__dot${ i === index ? ' is-current' : '' }` } />
					) ) }
				</div>
				{ isFirst ? (
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Skip tour', 'yaycheckout' ) }
					</Button>
				) : (
					<Button variant="secondary" onClick={ () => go( index - 1 ) }>
						{ __( 'Back', 'yaycheckout' ) }
					</Button>
				) }
				{ step.watch && ! isChanged ? (
					<Button ref={ primary } variant="tertiary" onClick={ () => go( index + 1 ) }>
						{ __( 'Skip this step', 'yaycheckout' ) }
					</Button>
				) : (
					<Button ref={ primary } variant="primary" onClick={ () => ( isLast ? onClose() : go( index + 1 ) ) }>
						{ isFirst && ! isLast && startLabel }
						{ isLast && __( 'Finish', 'yaycheckout' ) }
						{ ! isFirst && ! isLast && __( 'Next', 'yaycheckout' ) }
					</Button>
				) }
			</div>
		</div>
	);

	return createPortal(
		anchored ? (
			<div className="yaycheckout-tour__anchored" style={ anchored }>
				{ card }
			</div>
		) : (
			<div className="yaycheckout-tour__centred">{ card }</div>
		),
		document.body
	);
}

export default function Tour( {
	steps,
	onClose,
	startLabel = __( 'Start tour', 'yaycheckout' ),
}: {
	steps: TourStep[];
	onClose: () => void;
	/** The first step's button. */
	startLabel?: string;
} ) {
	const [ index, setIndex ] = useState( 0 );
	const [ rect, setRect ] = useState< DOMRect | null >( null );

	const go = ( next: number ) => {
		// A guided last step moves on by itself: past the end, the tour is over.
		if ( next >= steps.length ) {
			onClose();
			return;
		}

		steps[ next ].open?.();
		setIndex( next );
	};

	return (
		<>
			<Spotlight rect={ rect } />
			<Step
				key={ steps[ index ].id }
				step={ steps[ index ] }
				index={ index }
				steps={ steps }
				go={ go }
				onClose={ onClose }
				onRect={ setRect }
				startLabel={ startLabel }
			/>
		</>
	);
}

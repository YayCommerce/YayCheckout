import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { BaseControl, FormTokenField, PanelBody, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlocksAllowedExcept } from '../../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../../shared/block-gap';
import type { BlockEditorSelectors } from '../../shared/section-fields';
import { stepTitle } from '../../shared/step-title';
import { TEXT_FORMATS_WITH_LIVE_VALUE } from '../../shared/text-formats';
import stepButtonMeta from '../step-button/block.json';
import stepsMeta from '../steps/block.json';
import { summarySourceNames, summarySources, type SummarySource } from './summary-sources';
import './editor.css';

const isSteps = ( type: { name: string } ) => type.name === stepsMeta.name;

interface StepAttributes {
	title: string;
	summarySeparator: string;
	summaryOmit: string[];
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: StepAttributes;
	setAttributes: ( attributes: Partial< StepAttributes > ) => void;
	clientId: string;
	context: { 'yaycheckout/showHeadings'?: boolean };
}

export default function Edit( { attributes, setAttributes, clientId, context }: EditProps ) {
	const { title, summarySeparator, summaryOmit, style } = attributes;
	// The storefront hides the header, and so does the canvas; the sidebar still edits the title Steps Progress shows.
	const headingsHidden = context[ 'yaycheckout/showHeadings' ] === false;
	const index = useSelect( ( select ) => select( blockEditorStore ).getBlockIndex( clientId ), [ clientId ] );
	const position = typeof index === 'number' && index >= 0 ? index + 1 : 1;
	const sourcesJson = useSelect(
		( select ) =>
			JSON.stringify( summarySources( select( blockEditorStore ) as BlockEditorSelectors, clientId ) ),
		[ clientId ]
	);
	const sources = useMemo( (): SummarySource[] => JSON.parse( sourcesJson ), [ sourcesJson ] );
	const omitted = summaryOmit
		.map( ( key ) => sources.find( ( source ) => source.key === key ) )
		.filter( ( source ): source is SummarySource => source !== undefined );
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch( blockEditorStore );

	// A field left the step: drop it, so the storefront never omits a field the step lost.
	useEffect( () => {
		if ( omitted.length !== summaryOmit.length ) {
			markNotPersistent();
			setAttributes( { summaryOmit: omitted.map( ( source ) => source.key ) } );
		}
	}, [ summaryOmit, omitted.length ] );

	const names = useMemo( () => summarySourceNames( sources ), [ sources ] );
	const keyOf = useMemo( () => new Map( [ ...names ].map( ( [ key, name ] ) => [ name, key ] ) ), [ names ] );

	// Margins, as core's flow layout prints Block spacing, so a child's own Margin setting wins.
	const css = useBlockGapCSS( `#block-${ clientId } > .yaycheckout-step__body`, style, true );
	const blockProps = useBlockProps( { className: 'yaycheckout-step' } );
	const allowedBlocks = useBlocksAllowedExcept( isSteps );
	const bodyProps = useInnerBlocksProps(
		{ className: 'yaycheckout-step__body' },
		{
			allowedBlocks,
			template: [ [ stepButtonMeta.name ] ],
			templateLock: false,
		}
	);

	const titleId = `yaycheckout-step-title-${ clientId }`;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<BaseControl
						id={ titleId }
						label={ __( 'Title', 'yaycheckout' ) }
						help={
							headingsHidden
								? __( 'Step headings are hidden. Steps Progress still shows this title.', 'yaycheckout' )
								: undefined
						}
					>
						{ /* Its own identifier, so selecting it doesn't also select the canvas title. */ }
						<RichText
							id={ titleId }
							identifier="sidebarTitle"
							tagName="div"
							className="yaycheckout-step__title-control"
							value={ title }
							onChange={ ( value: string ) => setAttributes( { title: value } ) }
							placeholder={ stepTitle( '' ) }
							allowedFormats={ TEXT_FORMATS_WITH_LIVE_VALUE }
						/>
					</BaseControl>
					<TextControl
						label={ __( 'Separator', 'yaycheckout' ) }
						help={ __( 'Printed between the values. Spaces count. Empty keeps the comma.', 'yaycheckout' ) }
						value={ summarySeparator }
						onChange={ ( value: string ) =>
							setAttributes( { summarySeparator: value === '' ? ', ' : value } )
						}
						__nextHasNoMarginBottom
					/>
					{ /* Values stay out in the order the fields were added; remove and re-add one to move it. */ }
					<FormTokenField
						label={ __( 'Leave fields out', 'yaycheckout' ) }
						value={ omitted.map( ( source ) => names.get( source.key ) ?? source.key ) }
						suggestions={ [ ...names.values() ] }
						onChange={ ( tokens ) =>
							setAttributes( {
								summaryOmit: tokens
									.map( ( token ) => keyOf.get( typeof token === 'string' ? token : token.value ) )
									.filter( ( key ): key is string => key !== undefined ),
							} )
						}
						__experimentalValidateInput={ ( token: string ) => keyOf.has( token ) }
						__experimentalExpandOnFocus
						__experimentalShowHowTo={ false }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<p className="components-base-control__help">
						{ sources.length === 0
							? __( 'Add fields to this step to leave any out of the summary.', 'yaycheckout' )
							: __( 'Chosen fields stay out of the completed-step summary.', 'yaycheckout' ) }
					</p>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ css && <style>{ css }</style> }
				{ ! headingsHidden && (
					<div className="yaycheckout-step__header">
						<span className="yaycheckout-step__index">{ position }</span>
						<RichText
							identifier="title"
							tagName="span"
							className="yaycheckout-step__title"
							value={ title }
							onChange={ ( value: string ) => setAttributes( { title: value } ) }
							placeholder={ stepTitle( '' ) }
							allowedFormats={ TEXT_FORMATS_WITH_LIVE_VALUE }
						/>
					</div>
				) }
				<div { ...bodyProps } />
			</div>
		</>
	);
}

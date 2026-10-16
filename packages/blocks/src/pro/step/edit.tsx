import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { BaseControl, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useBlocksAllowedExcept } from '../../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../../shared/block-gap';
import { stepTitle } from '../../shared/step-title';
import { TEXT_FORMATS_WITH_LIVE_VALUE } from '../../shared/text-formats';
import stepButtonMeta from '../step-button/block.json';
import stepsMeta from '../steps/block.json';
import './editor.css';

const isSteps = ( type: { name: string } ) => type.name === stepsMeta.name;

interface StepAttributes {
	title: string;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: StepAttributes;
	setAttributes: ( attributes: Partial< StepAttributes > ) => void;
	clientId: string;
	context: { 'yaycheckout/showHeadings'?: boolean };
}

export default function Edit( { attributes, setAttributes, clientId, context }: EditProps ) {
	const { title, style } = attributes;
	// The storefront hides the header, and so does the canvas; the sidebar still edits the title Steps Progress shows.
	const headingsHidden = context[ 'yaycheckout/showHeadings' ] === false;
	const index = useSelect( ( select ) => select( blockEditorStore ).getBlockIndex( clientId ), [ clientId ] );
	const position = typeof index === 'number' && index >= 0 ? index + 1 : 1;
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

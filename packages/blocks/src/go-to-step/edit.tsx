import { InspectorControls, RichText, store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import { stepTitle } from '../shared/step-title';
import stepsMeta from '../steps/block.json';

const STEPS = stepsMeta.name;

interface GoToStepAttributes {
	step: number;
	buttonText: string;
}

interface EditProps {
	attributes: GoToStepAttributes;
	setAttributes: ( attributes: Partial< GoToStepAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-go-to-step' } );
	// Mirrors CheckoutStepsProgress::layout_titles(): the layout's first Steps block, in document order.
	const titles: string[] | null = useSelect( ( select ) => {
		const { getBlockAttributes, getBlockOrder, getBlocksByName } = select( blockEditorStore );
		const stepsId = ( getBlocksByName( STEPS ) as string[] )[ 0 ];

		if ( ! stepsId ) {
			return null;
		}

		return ( getBlockOrder( stepsId ) as string[] ).map( ( id ) => {
			const title = getBlockAttributes( id )?.title;

			return typeof title === 'string' ? title : '';
		} );
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Go to step', 'yaycheckout' ) }>
					{ titles === null ? (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add a Steps block to use Go to Step.', 'yaycheckout' ) }
						</Notice>
					) : (
						<>
							<SelectControl
								label={ __( 'Step', 'yaycheckout' ) }
								value={ String( attributes.step ) }
								options={ titles.map( ( title, index ) => ( {
									label: `${ index + 1 }. ${ stepTitle( decodeEntities( title ) ) }`,
									value: String( index + 1 ),
								} ) ) }
								onChange={ ( value: string ) => setAttributes( { step: Number( value ) } ) }
							/>
							{ attributes.step > titles.length && (
								<Notice status="warning" isDismissible={ false }>
									{ sprintf(
										/* translators: %d: saved step number. */
										__( 'Step %d no longer exists. Pick another step.', 'yaycheckout' ),
										attributes.step
									) }
								</Notice>
							) }
						</>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ /* A div, not a button: typing Space inside a button would click it. The placeholder mirrors render.php's fallback label. */ }
				<RichText
					tagName="div"
					className="yaycheckout-go-to-step__button"
					value={ attributes.buttonText }
					onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
					placeholder={ __( 'Edit', 'yaycheckout' ) }
					allowedFormats={ [] }
					withoutInteractiveFormatting
				/>
			</div>
		</>
	);
}

import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './section-preview.css';

interface SectionLabelProps {
	panelTitle: string;
	label: string;
	/** The heading while the label is empty, as render.php prints it. */
	defaultLabel: string;
	setAttributes: ( attributes: { label: string } ) => void;
	/** False when render.php prints no heading, so only the sidebar field shows. */
	showHeading?: boolean;
	help?: string;
}

/** A section's heading on the canvas and its Label field in the sidebar. */
export function SectionLabel( {
	panelTitle,
	label,
	defaultLabel,
	setAttributes,
	showHeading = true,
	help,
}: SectionLabelProps ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ panelTitle }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						help={ help }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ showHeading && <h3 className="yaycheckout-section-preview__heading">{ label || defaultLabel }</h3> }
		</>
	);
}

import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './section-preview.css';

interface SectionLabelProps {
	label: string;
	/** The heading while the label is empty, as render.php prints it. */
	defaultLabel: string;
	setAttributes: ( attributes: { label: string } ) => void;
}

/** A section's heading on the canvas and its Label field in the sidebar. */
export function SectionLabel( { label, defaultLabel, setAttributes }: SectionLabelProps ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<h3 className="yaycheckout-section-preview__heading">{ label || defaultLabel }</h3>
		</>
	);
}

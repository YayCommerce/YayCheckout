import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

interface PlaceOrderAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: PlaceOrderAttributes;
}

/**
 * The button is a locked core/button, so its settings, inline text and pseudo
 * states come from core. buttonText only seeds layouts saved before this.
 */
export default function Edit( { attributes }: EditProps ) {
	const lock = { move: true, remove: true };
	const innerBlocksProps = useInnerBlocksProps( useBlockProps( { className: 'yaycheckout-place-order' } ), {
		template: [
			[
				'core/buttons',
				{ lock },
				[
					[
						'core/button',
						{
							tagName: 'button',
							type: 'submit',
							text: attributes.buttonText || __( 'Place order', 'yaycheckout' ),
							style: { dimensions: { width: '100%' } },
							lock,
						},
					],
				],
			],
		],
		templateLock: 'all',
	} );

	return <div { ...innerBlocksProps } />;
}

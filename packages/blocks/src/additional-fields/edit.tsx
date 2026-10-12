import { store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import orderNoteMeta from '../order-note/block.json';
import { ORDER_NOTE_LABEL, ORDER_NOTE_PLACEHOLDER } from '../order-note/defaults';
import { FieldPreview } from '../shared/field-preview';

/**
 * Static preview of WooCommerce's order fieldset; the storefront prints whatever WooCommerce
 * and plugins put there (render.php). With an Order note block in the layout, the note is left out.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-additional-fields' } );
	const hasOrderNote = useSelect(
		( select ) => select( blockEditorStore ).getBlocksByName( orderNoteMeta.name ).length > 0,
		[]
	);

	return (
		<div { ...blockProps }>
			{ ! hasOrderNote && (
				<FieldPreview
					label={ ORDER_NOTE_LABEL }
					type="textarea"
					placeholder={ ORDER_NOTE_PLACEHOLDER }
				/>
			) }
			<Notice status="info" isDismissible={ false }>
				{ __(
					"Shows WooCommerce's order fields, including any a plugin adds, with the labels and required settings WooCommerce gives them.",
					'yaycheckout'
				) }
				{ hasOrderNote &&
					' ' +
						__(
							'The Order note block in this layout shows the order note, so this block leaves it out.',
							'yaycheckout'
						) }
			</Notice>
		</div>
	);
}

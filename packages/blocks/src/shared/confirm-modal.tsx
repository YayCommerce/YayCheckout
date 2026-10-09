import { Button, Flex, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor-panel.css';

/** A small confirm dialog: the lines say what confirming changes, Cancel closes it. */
export function ConfirmModal( { title, lines, confirmLabel, onConfirm, onCancel }: { title: string; lines: string[]; confirmLabel: string; onConfirm: () => void; onCancel: () => void } ) {
	return (
		<Modal title={ title } size="small" onRequestClose={ onCancel }>
			<ul className="yaycheckout-confirm-modal__lines">
				{ lines.map( ( line ) => (
					<li key={ line }>{ line }</li>
				) ) }
			</ul>
			<Flex justify="flex-end" gap={ 2 }>
				<Button __next40pxDefaultSize variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'yaycheckout' ) }
				</Button>
				<Button __next40pxDefaultSize variant="primary" onClick={ onConfirm }>
					{ confirmLabel }
				</Button>
			</Flex>
		</Modal>
	);
}

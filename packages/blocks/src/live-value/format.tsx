import { RichTextToolbarButton, store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerFormatType, type RichTextValue } from '@wordpress/rich-text';
import { FALLBACK_ATTR, KEY_ATTR, LIVE_VALUE_BLOCKS, LIVE_VALUE_CLASS, LIVE_VALUE_FORMAT, PREFIX_ATTR, SUFFIX_ATTR } from './constants';
import LiveValuePopover, { type LiveValueAttributes } from './popover';

type FormatEditProps = {
	value: RichTextValue;
	onChange: ( value: RichTextValue ) => void;
	onFocus: () => void;
	contentRef: { current: HTMLElement | null };
	/** True while the selection is one chip of this format. */
	isObjectActive: boolean;
	activeObjectAttributes: LiveValueAttributes;
};

function Edit( { value, onChange, onFocus, contentRef, isObjectActive, activeObjectAttributes }: FormatEditProps ) {
	const blockName = useSelect( ( select ) => {
		const editor = select( blockEditorStore );
		const clientId = editor.getSelectedBlockClientId();

		return clientId ? editor.getBlockName( clientId ) : null;
	}, [] );
	const [ adding, setAdding ] = useState( false );
	// The chip whose popover the merchant closed; it stays selected, so it would reopen at once otherwise.
	const [ dismissed, setDismissed ] = useState< number | null >( null );

	useEffect( () => {
		if ( ! isObjectActive ) {
			setDismissed( null );
		}
	}, [ isObjectActive ] );

	if ( ! blockName || ! LIVE_VALUE_BLOCKS.includes( blockName ) ) {
		return null;
	}

	const editing = isObjectActive && dismissed !== value.start;

	const close = () => {
		setAdding( false );

		if ( isObjectActive ) {
			setDismissed( value.start );
		}

		onFocus();
	};

	return (
		<>
			<RichTextToolbarButton
				icon="database"
				title={ __( 'Live value', 'yaycheckout' ) }
				isActive={ isObjectActive }
				onClick={ () => {
					setDismissed( null );
					setAdding( true );
				} }
			/>
			{ ( adding || editing ) && (
				<LiveValuePopover
					// A fresh form for each chip, and for a new one.
					key={ isObjectActive ? `chip-${ value.start }` : 'new' }
					value={ value }
					onChange={ onChange }
					contentRef={ contentRef }
					editing={ isObjectActive ? activeObjectAttributes : undefined }
					onClose={ close }
				/>
			) }
		</>
	);
}

registerFormatType( LIVE_VALUE_FORMAT, {
	title: __( 'Live value', 'yaycheckout' ),
	tagName: 'span',
	className: LIVE_VALUE_CLASS,
	attributes: { value: KEY_ATTR, fallback: FALLBACK_ATTR, prefix: PREFIX_ATTR, suffix: SUFFIX_ATTR },
	// One uneditable character in the text, as core's footnotes are: undo and delete take the whole chip.
	contentEditable: false,
	interactive: true,
	edit: Edit,
} );

import { Button, Flex, Popover, SelectControl, TextControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import { insertObject, remove, useAnchor, type RichTextValue } from '@wordpress/rich-text';
import { editorData } from '../shared/editor-data';
import { fieldNames, useShowableFields } from '../shared/showable-fields';
import { FIELD_PREFIX, LIVE_VALUE_CLASS, LIVE_VALUE_FORMAT } from './constants';

/** LiveValues::cart_values() keys ending in this are counts; the rest are money. */
const COUNT_SUFFIX = '_count';

const CART_GROUPS = [
	{ label: __( 'Cart totals', 'yaycheckout' ), counts: false },
	{ label: __( 'Counts', 'yaycheckout' ), counts: true },
];

interface LiveValuePopoverProps {
	value: RichTextValue;
	onChange: ( value: RichTextValue ) => void;
	contentRef: { current: HTMLElement | null };
	/** The chip being edited, selected in `value`; undefined while inserting a new one. */
	editing?: { value?: string; fallback?: string };
	onClose: () => void;
}

const ANCHOR_SETTINGS = { tagName: 'span', className: LIVE_VALUE_CLASS };

export default function LiveValuePopover( { value, onChange, contentRef, editing, onClose }: LiveValuePopoverProps ) {
	const [ key, setKey ] = useState( editing?.value ?? '' );
	const [ fallback, setFallback ] = useState( editing?.fallback ?? '' );
	const anchor = useAnchor( { editableContentElement: contentRef.current, settings: ANCHOR_SETTINGS } );
	const fields = useShowableFields();
	const names = useMemo( () => fieldNames( fields ), [ fields ] );
	const cart = Object.entries( editorData.liveValues );

	const labelOf = ( picked: string ): string =>
		picked.startsWith( FIELD_PREFIX ) ? names.get( picked.slice( FIELD_PREFIX.length ) ) ?? picked : editorData.liveValues[ picked ] ?? picked;

	const save = () => {
		// Inserting replaces the selection; editing replaces the selected chip, which is the selection.
		onChange(
			insertObject( value, {
				type: LIVE_VALUE_FORMAT,
				attributes: { value: key, fallback },
				innerHTML: escapeHTML( labelOf( key ) ),
			} )
		);
		onClose();
	};

	return (
		<Popover
			anchor={ anchor }
			placement="bottom"
			focusOnMount="firstElement"
			onClose={ onClose }
			className="yaycheckout-live-value-popover"
		>
			<Flex direction="column" align="stretch" gap={ 3 } className="yaycheckout-live-value-popover__body">
				<SelectControl
					label={ __( 'Value', 'yaycheckout' ) }
					value={ key }
					onChange={ ( next: string ) => setKey( next ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				>
					<option value="" disabled>
						{ __( 'Select a value', 'yaycheckout' ) }
					</option>
					{ CART_GROUPS.map( ( { label, counts } ) => (
						<optgroup key={ label } label={ label }>
							{ cart
								.filter( ( [ cartKey ] ) => cartKey.endsWith( COUNT_SUFFIX ) === counts )
								.map( ( [ cartKey, cartLabel ] ) => (
									<option key={ cartKey } value={ cartKey }>
										{ cartLabel }
									</option>
								) ) }
						</optgroup>
					) ) }
					{ names.size > 0 && (
						<optgroup label={ __( 'Fields', 'yaycheckout' ) }>
							{ [ ...names ].map( ( [ fieldKey, name ] ) => (
								<option key={ fieldKey } value={ FIELD_PREFIX + fieldKey }>
									{ name }
								</option>
							) ) }
						</optgroup>
					) }
				</SelectControl>
				<TextControl
					label={ __( 'Fallback when empty', 'yaycheckout' ) }
					value={ fallback }
					onChange={ ( next: string ) => setFallback( next ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<Flex justify="flex-end">
					{ editing && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => {
								onChange( remove( value, value.start, value.start + 1 ) );
								onClose();
							} }
						>
							{ __( 'Remove', 'yaycheckout' ) }
						</Button>
					) }
					<Button variant="primary" disabled={ key === '' } onClick={ save }>
						{ editing ? __( 'Update', 'yaycheckout' ) : __( 'Insert', 'yaycheckout' ) }
					</Button>
				</Flex>
			</Flex>
		</Popover>
	);
}

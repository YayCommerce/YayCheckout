import type { FieldOption } from '../shared/field-preview';

export type CheckboxMode = 'single' | 'multiple';

/**
 * The Checkbox type's mode: the attribute when set, else 'multiple' when the field has a usable option,
 * as before the attribute existed. Copies LayoutMarkupReader::checkbox_multiple(); change them together.
 */
export function checkboxMode( mode: string | undefined, options: FieldOption[] ): CheckboxMode {
	if ( mode === 'single' || mode === 'multiple' ) {
		return mode;
	}

	return options.some( ( option ) => option.label.trim() !== '' || option.value.trim() !== '' ) ? 'multiple' : 'single';
}

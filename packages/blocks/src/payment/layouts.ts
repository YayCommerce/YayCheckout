import { __ } from '@wordpress/i18n';
import { COLOR_SETTING_KEYS, type PaymentSettingKey } from './setting-controls';

export interface PaymentLayout {
	value: string;
	label: string;
	settings: PaymentSettingKey[];
}

/** Layouts whose rows have their own outline take every colour. */
const ROW_COLORS: PaymentSettingKey[] = [ ...COLOR_SETTING_KEYS ];

/** Divided and Boxed rows share lines, so they take one border colour. */
const SHARED_LINE_COLORS: PaymentSettingKey[] = ROW_COLORS.filter(
	( key ) => key !== 'borderColorSelected' && key !== 'borderColorHover'
);

/** Mirrors CheckoutFragments::PAYMENT_LAYOUTS in PHP. */
export const PAYMENT_LAYOUTS: PaymentLayout[] = [
	{
		value: 'default',
		label: __( 'Default', 'yaycheckout' ),
		settings: [ 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'check',
		label: __( 'Check', 'yaycheckout' ),
		settings: [ 'gap', 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'boxed',
		label: __( 'Boxed', 'yaycheckout' ),
		settings: [ 'borderWidth', 'borderRadius', ...SHARED_LINE_COLORS ],
	},
	{
		value: 'columns',
		label: __( 'Columns', 'yaycheckout' ),
		settings: [ 'gap', 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'stacked',
		label: __( 'Stacked', 'yaycheckout' ),
		settings: [ 'gap', 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'divided',
		label: __( 'Divided', 'yaycheckout' ),
		settings: [ 'borderWidth', ...SHARED_LINE_COLORS ],
	},
];

/** The layout for a stored value, or Default when missing or unknown. */
export function layoutFor( value: string | undefined ): PaymentLayout {
	return PAYMENT_LAYOUTS.find( ( layout ) => layout.value === value ) ?? PAYMENT_LAYOUTS[ 0 ];
}

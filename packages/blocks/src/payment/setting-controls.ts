import { __ } from '@wordpress/i18n';

export type PaymentSettingKey =
	| 'gap'
	| 'borderWidth'
	| 'borderRadius'
	| 'backgroundColor'
	| 'backgroundColorSelected'
	| 'backgroundColorHover'
	| 'borderColor'
	| 'borderColorSelected'
	| 'borderColorHover'
	| 'radioColor'
	| 'radioColorSelected';

/** Every colour key, in the order the Styles panels list them. */
export const COLOR_SETTING_KEYS = [
	'backgroundColor',
	'backgroundColorSelected',
	'backgroundColorHover',
	'radioColor',
	'radioColorSelected',
	'borderColor',
	'borderColorSelected',
	'borderColorHover',
] as const satisfies readonly PaymentSettingKey[];

export type ColorSettingKey = ( typeof COLOR_SETTING_KEYS )[ number ];

export function isColorSetting( key: PaymentSettingKey ): key is ColorSettingKey {
	return ( COLOR_SETTING_KEYS as readonly string[] ).includes( key );
}

/**
 * Colours shown as one row with a tab per state, the Default first (StateColourItem). A layout that lists only
 * a row's Default shows it as a plain colour.
 */
export const COLOR_STATE_GROUPS: ColorSettingKey[][] = [
	[ 'backgroundColor', 'backgroundColorSelected', 'backgroundColorHover' ],
	[ 'radioColor', 'radioColorSelected' ],
	[ 'borderColor', 'borderColorSelected', 'borderColorHover' ],
];

/** Each setting's label inside its Styles panel; a state's is its tab's, in its COLOR_STATE_GROUPS row. */
export const SETTING_LABELS: Record< PaymentSettingKey, string > = {
	gap: __( 'Gap', 'yaycheckout' ),
	borderWidth: __( 'Width', 'yaycheckout' ),
	borderRadius: __( 'Radius', 'yaycheckout' ),
	backgroundColor: __( 'Background', 'yaycheckout' ),
	backgroundColorSelected: __( 'Selected', 'yaycheckout' ),
	backgroundColorHover: __( 'Hover', 'yaycheckout' ),
	radioColor: __( 'Radio', 'yaycheckout' ),
	radioColorSelected: __( 'Selected', 'yaycheckout' ),
	borderColor: __( 'Color', 'yaycheckout' ),
	borderColorSelected: __( 'Selected', 'yaycheckout' ),
	borderColorHover: __( 'Hover', 'yaycheckout' ),
};

export interface SettingsPanel {
	id: string;
	title: string;
	/** In display order: colours first, then ranges. */
	keys: PaymentSettingKey[];
}

/** The Styles tab panels. Each shows only the keys the selected layout lists. */
export const SETTING_PANELS: SettingsPanel[] = [
	{ id: 'methods', title: __( 'Methods', 'yaycheckout' ), keys: [ 'gap' ] },
	{
		id: 'colors',
		title: __( 'Methods colors', 'yaycheckout' ),
		keys: [ 'backgroundColor', 'backgroundColorSelected', 'backgroundColorHover', 'radioColor', 'radioColorSelected' ],
	},
	{
		id: 'border',
		title: __( 'Methods border', 'yaycheckout' ),
		keys: [ 'borderColor', 'borderColorSelected', 'borderColorHover', 'borderWidth', 'borderRadius' ],
	},
];

/** Px ranges for the non-colour settings. */
export const RANGE_LIMITS: Partial< Record< PaymentSettingKey, number > > = {
	gap: 48,
	borderWidth: 8,
	borderRadius: 32,
};

/** A stored px length ("12px" or 12) as a number. */
export function pxNumber( value: unknown ): number | undefined {
	if ( typeof value === 'number' && Number.isFinite( value ) ) {
		return value;
	}
	if ( typeof value === 'string' ) {
		const parsed = parseInt( value, 10 );
		return Number.isFinite( parsed ) ? parsed : undefined;
	}
	return undefined;
}

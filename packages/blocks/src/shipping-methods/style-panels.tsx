import {
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	RangeControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	COLOR_STATE_GROUPS,
	isColorSetting,
	pxNumber,
	RANGE_LIMITS,
	SETTING_LABELS,
	SETTING_PANELS,
	type ShippingSettingKey,
} from './setting-controls';
import { getStyleSetting, setStyleSetting } from './style-paths';
import { StateColourItem } from '../shared/state-colour-item';
import '../shared/tools-panel.css';
import { hasValue, type StyleObject } from '../shared/style-path';

interface StylePanelsProps {
	/** The selected layout's settings. */
	keys: ShippingSettingKey[];
	style: StyleObject | undefined;
	onChange: ( style: StyleObject | undefined ) => void;
}

/**
 * The Shipping Methods Styles tab as native ToolsPanels, like a block's Color panel: each item resets from the
 * panel menu, Reset all clears the panel, and colour pickers have Clear.
 */
export function StylePanels( { keys, style, onChange }: StylePanelsProps ) {
	const palettes = useMultipleOriginColorsAndGradients();
	// Reset all calls back once per item before re-rendering, so each change must build on the last one.
	const latest = useRef( style );
	latest.current = style;

	const set = ( key: ShippingSettingKey, value: unknown ) => {
		latest.current = setStyleSetting( latest.current, key, value );
		onChange( latest.current );
	};

	/** A colour setting's stored value for the picker; none when unset. */
	const colorOf = ( key: ShippingSettingKey ) => {
		const value = getStyleSetting( style, key );
		return typeof value === 'string' && value !== '' ? value : undefined;
	};

	return (
		<>
			{ SETTING_PANELS.map( ( panel ) => {
				const shown = panel.keys.filter( ( key ) => keys.includes( key ) );
				if ( shown.length === 0 ) {
					return null;
				}

				const panelId = `yaycheckout-shipping-methods-${ panel.id }`;
				const colorKeys = shown.filter( isColorSetting );
				const rangeKeys = shown.filter( ( key ) => ! isColorSetting( key ) );
				// Each row's states the layout lists: two or more are one tabbed item, a Default alone a plain one.
				const rows = COLOR_STATE_GROUPS.map( ( group ) => group.filter( ( key ) => colorKeys.includes( key ) ) );
				const plainKeys = rows.filter( ( row ) => row.length === 1 ).flat();
				const stateRows = rows.filter( ( row ) => row.length > 1 );

				return (
					<ToolsPanel
						key={ panel.id }
						label={ panel.title }
						panelId={ panelId }
						className="yaycheckout-tools-panel"
						resetAll={ () => shown.forEach( ( key ) => set( key, undefined ) ) }
					>
						{ colorKeys.length > 0 && (
							<div className="yaycheckout-tools-panel__colors">
								{ plainKeys.length > 0 && (
									<ColorGradientSettingsDropdown
										__experimentalIsRenderedInSidebar
										panelId={ panelId }
										settings={ plainKeys.map( ( key ) => ( {
											label: SETTING_LABELS[ key ],
											colorValue: colorOf( key ),
											onColorChange: ( next?: string ) => set( key, next || undefined ),
											clearable: true,
											isShownByDefault: true,
										} ) ) }
										colors={ palettes.colors }
										disableCustomColors={ palettes.disableCustomColors }
										enableAlpha
									/>
								) }
								{ stateRows.map( ( row ) => (
									<StateColourItem
										key={ row[ 0 ] }
										label={ SETTING_LABELS[ row[ 0 ] ] }
										panelId={ panelId }
										states={ row.map( ( key, index ) => ( {
											key,
											label: index === 0 ? __( 'Default', 'yaycheckout' ) : SETTING_LABELS[ key ],
											colorValue: colorOf( key ),
											onColorChange: ( next?: string ) => set( key, next || undefined ),
										} ) ) }
										colors={ palettes.colors }
										disableCustomColors={ palettes.disableCustomColors }
										enableAlpha
										onReset={ () => row.forEach( ( key ) => set( key, undefined ) ) }
									/>
								) ) }
							</div>
						) }
						{ rangeKeys.map( ( key ) => (
							<ToolsPanelItem
								key={ key }
								panelId={ panelId }
								label={ SETTING_LABELS[ key ] }
								hasValue={ () => hasValue( getStyleSetting( style, key ) ) }
								onDeselect={ () => set( key, undefined ) }
								isShownByDefault
							>
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ SETTING_LABELS[ key ] }
									value={ pxNumber( getStyleSetting( style, key ) ) }
									onChange={ ( next ) => set( key, next === undefined ? undefined : `${ next }px` ) }
									min={ 0 }
									max={ RANGE_LIMITS[ key ] ?? 32 }
								/>
							</ToolsPanelItem>
						) ) }
					</ToolsPanel>
				);
			} ) }
		</>
	);
}

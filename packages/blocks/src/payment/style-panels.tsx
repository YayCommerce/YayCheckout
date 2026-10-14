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
import {
	isColorSetting,
	pxNumber,
	RANGE_LIMITS,
	SETTING_LABELS,
	SETTING_PANELS,
	type PaymentSettingKey,
} from './setting-controls';
import { getStyleSetting, setStyleSetting } from './style-paths';
import { hasValue, type StyleObject } from '../shared/style-path';
import { StyleSection } from '../shared/style-section';
import '../shared/tools-panel.css';

interface StylePanelsProps {
	/** The selected layout's settings. */
	keys: PaymentSettingKey[];
	style: StyleObject | undefined;
	onChange: ( style: StyleObject | undefined ) => void;
}

/**
 * The Payment Styles tab as native ToolsPanels, like a block's Color panel: each item resets from the
 * panel menu, Reset all clears the panel, and colour pickers have Clear.
 */
export function StylePanels( { keys, style, onChange }: StylePanelsProps ) {
	const palettes = useMultipleOriginColorsAndGradients();
	// Reset all calls back once per item before re-rendering, so each change must build on the last one.
	const latest = useRef( style );
	latest.current = style;

	const set = ( key: PaymentSettingKey, value: unknown ) => {
		latest.current = setStyleSetting( latest.current, key, value );
		onChange( latest.current );
	};

	return (
		<>
			{ SETTING_PANELS.map( ( panel ) => {
				const shown = panel.keys.filter( ( key ) => keys.includes( key ) );
				if ( shown.length === 0 ) {
					return null;
				}

				const panelId = `yaycheckout-payment-${ panel.id }`;
				const colorKeys = shown.filter( isColorSetting );
				const rangeKeys = shown.filter( ( key ) => ! isColorSetting( key ) );

				return (
					<StyleSection key={ panel.id } title={ panel.title }>
						<ToolsPanel
							label={ panel.title }
							panelId={ panelId }
							className="yaycheckout-tools-panel"
							resetAll={ () => shown.forEach( ( key ) => set( key, undefined ) ) }
						>
							{ colorKeys.length > 0 && (
								<div className="yaycheckout-tools-panel__colors">
									<ColorGradientSettingsDropdown
										__experimentalIsRenderedInSidebar
										panelId={ panelId }
										settings={ colorKeys.map( ( key ) => {
											const value = getStyleSetting( style, key );
											return {
												label: SETTING_LABELS[ key ],
												colorValue: typeof value === 'string' && value !== '' ? value : undefined,
												onColorChange: ( next?: string ) => set( key, next || undefined ),
												clearable: true,
												isShownByDefault: true,
											};
										} ) }
										colors={ palettes.colors }
										disableCustomColors={ palettes.disableCustomColors }
										enableAlpha
									/>
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
					</StyleSection>
				);
			} ) }
		</>
	);
}

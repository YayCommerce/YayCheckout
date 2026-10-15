import { __experimentalColorGradientControl as ColorGradientControl } from '@wordpress/block-editor';
import {
	Button,
	ColorIndicator,
	Dropdown,
	Flex,
	FlexItem,
	Path,
	SVG,
	TabPanel,
	__experimentalDropdownContentWrapper as DropdownContentWrapper,
	__experimentalHStack as HStack,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalZStack as ZStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** Where core opens a sidebar colour popover. */
const SIDEBAR_POPOVER = { placement: 'left-start', offset: 36, shift: true } as const;

/** Core's reset icon (@wordpress/icons `reset`), which the blocks don't bundle. */
const RESET_ICON = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<Path d="M7 11.5h10V13H7z" />
	</SVG>
);

/** One palette origin (Theme, Default, Custom) as useMultipleOriginColorsAndGradients() returns it. */
export type PaletteOrigin = {
	name: string;
	slug: string;
	colors?: { name?: string; slug: string; color?: string }[];
};

export type ColourState = {
	/** The setting this state stores, unique within the item. */
	key: string;
	/** The tab's label, such as Default or Hover. */
	label: string;
	colorValue?: string;
	colorSlug?: string;
	onColorChange: ( next?: string, slug?: string ) => void;
};

/**
 * One colour with a tab per state (Default, Hover, Selected, Focus), as core's block Elements → Link item: a row
 * with an indicator per state, and a popover with a tab per state. Built from public components, since core's
 * own (ColorGradientDropdownItem) is private; the classes are core's, so the row matches the colour items beside
 * it. Reset, from the row or the panel menu, clears every state.
 */
export function StateColourItem( {
	label,
	panelId,
	states,
	colors,
	disableCustomColors,
	enableAlpha = false,
	onReset,
}: {
	label: string;
	panelId: string;
	states: ColourState[];
	colors: PaletteOrigin[] | undefined;
	disableCustomColors?: boolean;
	enableAlpha?: boolean;
	onReset: () => void;
} ) {
	const hasValue = () => states.some( ( state ) => undefined !== state.colorValue );

	return (
		<ToolsPanelItem
			className="block-editor-color-gradient-item block-editor-tools-panel-color-gradient-settings__item"
			hasValue={ hasValue }
			label={ label }
			onDeselect={ onReset }
			isShownByDefault
			panelId={ panelId }
		>
			<Dropdown
				popoverProps={ SIDEBAR_POPOVER }
				className="block-editor-tools-panel-color-gradient-settings__dropdown"
				renderToggle={ ( { onToggle, isOpen } ) => (
					<>
						<Button
							__next40pxDefaultSize
							onClick={ onToggle }
							className={ `block-editor-panel-color-gradient-settings__dropdown${ isOpen ? ' is-open' : '' }` }
							aria-expanded={ isOpen }
						>
							<HStack justify="flex-start">
								<ZStack isLayered={ false } offset={ -8 }>
									{ states.map( ( state ) => (
										<Flex key={ state.key } expanded={ false }>
											<ColorIndicator colorValue={ state.colorValue } />
										</Flex>
									) ) }
								</ZStack>
								<FlexItem className="block-editor-panel-color-gradient-settings__color-name" title={ label }>
									{ label }
								</FlexItem>
							</HStack>
						</Button>
						{ hasValue() && (
							<Button
								__next40pxDefaultSize
								label={ __( 'Reset', 'yaycheckout' ) }
								className="block-editor-panel-color-gradient-settings__reset"
								size="small"
								icon={ RESET_ICON }
								onClick={ () => {
									onReset();
									if ( isOpen ) {
										onToggle();
									}
								} }
							/>
						) }
					</>
				) }
				renderContent={ () => (
					<DropdownContentWrapper paddingSize="none">
						<div className="block-editor-panel-color-gradient-settings__dropdown-content">
							<TabPanel tabs={ states.map( ( state ) => ( { name: state.key, title: state.label } ) ) }>
								{ ( tab ) => {
									const state = states.find( ( item ) => item.key === tab.name ) ?? states[ 0 ];

									return (
										<ColorGradientControl
											key={ state.key }
											showTitle={ false }
											enableAlpha={ enableAlpha }
											__experimentalIsRenderedInSidebar
											colors={ colors }
											disableCustomColors={ disableCustomColors }
											colorValue={ state.colorValue }
											colorSlug={ state.colorSlug }
											onColorChange={ state.onColorChange }
											clearable={ undefined !== state.colorValue }
											headingLevel={ 3 }
										/>
									);
								} }
							</TabPanel>
						</div>
					</DropdownContentWrapper>
				) }
			/>
		</ToolsPanelItem>
	);
}

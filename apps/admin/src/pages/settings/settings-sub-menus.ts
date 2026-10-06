/** Settings tabs, in rail order. The router validates :subMenu against this; it lives here to avoid an import cycle. */
export const SETTINGS_SUB_MENUS = ['general', 'integrations', 'advanced'] as const;
export type SettingsSubMenu = (typeof SETTINGS_SUB_MENUS)[number];

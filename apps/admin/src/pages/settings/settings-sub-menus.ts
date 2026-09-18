// Rail order. Outside router.tsx to avoid a router ↔ page import cycle.
/** Settings tabs, in rail order. The router validates :subMenu against this. */
export const SETTINGS_SUB_MENUS = ['general', 'integrations', 'advanced'] as const;
export type SettingsSubMenu = (typeof SETTINGS_SUB_MENUS)[number];

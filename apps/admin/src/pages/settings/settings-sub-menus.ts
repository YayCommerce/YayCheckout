/**
 * Settings tabs, in rail order. The router validates :subMenu against this
 * list and the page renders one trigger per entry. Lives outside router.tsx
 * so the page can import it without a router ↔ page import cycle.
 */
export const SETTINGS_SUB_MENUS = ['general', 'integrations', 'advanced'] as const;
export type SettingsSubMenu = (typeof SETTINGS_SUB_MENUS)[number];

import { IntegrationWire, SettingsResponseWire } from '@/lib/schema/settings.schema';

export type Meta = {
  wpMeta: {
    siteUrl: string;
    adminUrl: string;
    ajaxUrl: string;

    restRoot: string;
    restBase: string;
    restNonce: string;
    isBlockTheme: boolean;
  };
  checkoutMeta: {
    pluginUrl: string;
    assetsUrl: string;

    version: string;
    docsUrl: string;
    upgradeUrl: string;
    reviewed: boolean;
    showSetupWizard: boolean;
  };
};

/** Wire shapes, as PHP localizes them. */
export type AdminData = {
  settings: SettingsResponseWire;
  integrations: IntegrationWire[];
  /** Pro only: Free has no Assignment drawer. */
  store?: StoreData;
};

/** Mirrors AssignmentStoreData::get() in PHP. */
export type StoreData = {
  /** Role slug => translated name, every registered role. */
  roles: Record<string, string>;
};

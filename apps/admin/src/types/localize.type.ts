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
    reviewed: boolean;
  };
};

/** Wire shapes; parsed by settings.queries.ts before use. */
export type AdminData = {
  settings: SettingsResponseWire;
  integrations: IntegrationWire[];
};

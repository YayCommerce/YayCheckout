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
    /** Product documentation root; feature pages hang off it. */
    docsUrl: string;
    reviewed: boolean;
  };
};

/** Raw localize payload — wire shapes, parsed by lib/queries/settings.queries.ts before use. */
export type AdminData = {
  settings: SettingsResponseWire;
  integrations: IntegrationWire[];
};

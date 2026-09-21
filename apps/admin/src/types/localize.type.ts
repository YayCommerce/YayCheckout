import { DesignResponseWire } from '@/lib/schema/design.schema';
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

/** Wire shapes; parsed by the matching *.queries.ts before use. */
export type AdminData = {
  settings: SettingsResponseWire;
  integrations: IntegrationWire[];
  design: DesignResponseWire;
};

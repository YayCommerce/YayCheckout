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
    upgradeUrl: string;
    reviewed: boolean;
  };
};

/** Wire shapes; parsed by the matching *.queries.ts before use. */
export type AdminData = {
  settings: SettingsResponseWire;
  integrations: IntegrationWire[];
  design: DesignResponseWire;
  /** ISO 3166-1 alpha-2 code => name, in Woo's display order. */
  countries: Record<string, string>;
  /** Role slug => translated name, every registered role. */
  roles: Record<string, string>;
  /** Payment gateway id => admin name, every classic gateway, enabled or not. */
  gateways: Record<string, string>;
  /** Woo's price format settings. */
  currency: {
    symbol: string;
    position: 'left' | 'right' | 'left_space' | 'right_space';
    decimals: number;
    decimal_separator: string;
    thousand_separator: string;
  };
};

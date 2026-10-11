import { __ } from '@wordpress/i18n';

import type { CompatibilityReport, GatewayRow, PluginRow, StoreEnvironment } from '@/lib/schema/compatibility.schema';

export type CompatibilityWarnings = {
  blockOnly: GatewayRow[];
  unverifiedPlugins: PluginRow[];
};

/** A gateway that only works on WooCommerce's block checkout, so not in a layout. */
export function isBlockOnly(gateway: GatewayRow): boolean {
  return gateway.status === 'block-only';
}

/** What the banner counts. `community` is a hint, never a warning. */
export function deriveWarnings(report: CompatibilityReport): CompatibilityWarnings {
  return {
    blockOnly: report.gateways.filter(isBlockOnly),
    unverifiedPlugins: report.plugins.filter((plugin) => plugin.status === 'unverified'),
  };
}

/** How a store's checkout type reads to the merchant. */
export function storeTypeLabel(storeType: StoreEnvironment['storeType']): string {
  return storeType === 'block' ? __('Block checkout', 'yaycheckout') : __('Classic store', 'yaycheckout');
}

import type { CompatibilityReport, GatewayRow, PluginRow } from '@/lib/schema/compatibility.schema';

export type CompatibilityWarnings = {
  blockOnly: GatewayRow[];
  unverifiedPlugins: PluginRow[];
};

/** What the banner counts. `community` is a hint, never a warning. */
export function deriveWarnings(report: CompatibilityReport): CompatibilityWarnings {
  return {
    blockOnly: report.gateways.filter((gateway) => gateway.status === 'block-only'),
    unverifiedPlugins: report.plugins.filter((plugin) => plugin.status === 'unverified'),
  };
}

import { api } from '@/lib/api/api';
import {
  compatibilityWireSchema,
  type CompatibilityReport,
  type CompatibilityWire,
} from '@/lib/schema/compatibility.schema';

export function reportFromWire(wire: CompatibilityWire): CompatibilityReport {
  return {
    store: wire.store && {
      storeType: wire.store.store_type,
      woocommerceVersion: wire.store.woocommerce_version,
      wordpressVersion: wire.store.wordpress_version,
      theme: {
        name: wire.store.theme.name,
        version: wire.store.theme.version,
        isBlockTheme: wire.store.theme.is_block_theme,
      },
      safeMode: wire.store.safe_mode,
      activeLayouts: wire.store.active_layouts,
    },
    gateways:
      wire.gateways &&
      wire.gateways.map((gateway) => ({
        id: gateway.id,
        title: gateway.title,
        status: gateway.status,
        expressPay: gateway.express_pay,
        notes: gateway.notes,
        docsUrl: gateway.docs_url,
      })),
    plugins:
      wire.plugins &&
      wire.plugins.map((plugin) => ({
        slug: plugin.slug,
        name: plugin.name,
        version: plugin.version,
        kind: plugin.kind,
        touches: plugin.touches,
        status: plugin.status,
        notes: plugin.notes,
        docsUrl: plugin.docs_url,
      })),
    catalogue: {
      source: wire.catalogue.source,
      fetchedAt: wire.catalogue.fetched_at,
    },
  };
}

/** @param refresh Drop the cached catalogue and fetch it again before detecting. */
export async function getCompatibility(refresh: boolean): Promise<CompatibilityReport> {
  return reportFromWire(
    compatibilityWireSchema.parse(
      // No retry: a scan that failed once fails again, and each attempt reruns every detection.
      await api.get('compatibility', { retry: 0, ...(refresh && { searchParams: { refresh: 'true' } }) }).json(),
    ),
  );
}

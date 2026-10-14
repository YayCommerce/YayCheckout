import { api } from '@/lib/api/api';
import {
  checkoutProblemsWireSchema,
  compatibilityWireSchema,
  type CheckoutProblem,
  type CheckoutProblemWire,
  type CompatibilityReport,
  type CompatibilityWire,
  type StoreEnvironment,
} from '@/lib/schema/compatibility.schema';

function problemFromWire(problem: CheckoutProblemWire): CheckoutProblem {
  return {
    reason: problem.reason,
    detail: problem.detail,
    blocks: problem.blocks,
    layoutId: problem.layout_id,
    layoutTitle: problem.layout_title,
  };
}

export function reportFromWire(wire: CompatibilityWire): CompatibilityReport {
  return {
    store: {
      storeType: wire.store.store_type,
      woocommerceVersion: wire.store.woocommerce_version,
      wordpressVersion: wire.store.wordpress_version,
      theme: {
        name: wire.store.theme.name,
        version: wire.store.theme.version,
        isBlockTheme: wire.store.theme.is_block_theme,
      },
      safeMode: wire.store.safe_mode,
      checkoutProblems: wire.store.checkout_problems.map(problemFromWire),
    },
    gateways: wire.gateways.map((gateway) => ({
      id: gateway.id,
      title: gateway.title,
      status: gateway.status,
      expressPay: gateway.express_pay,
      notes: gateway.notes,
      docsUrl: gateway.docs_url,
    })),
    plugins: wire.plugins.map((plugin) => ({
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
      await api.get('compatibility', refresh ? { searchParams: { refresh: 'true' } } : undefined).json(),
    ),
  );
}

export async function getCheckoutProblems(): Promise<Pick<StoreEnvironment, 'safeMode' | 'checkoutProblems'>> {
  const wire = checkoutProblemsWireSchema.parse(await api.get('compatibility/problems').json());
  return { safeMode: wire.safe_mode, checkoutProblems: wire.checkout_problems.map(problemFromWire) };
}

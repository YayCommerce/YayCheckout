import { RefreshCw } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { deriveWarnings, FAILED_REPORT } from '@/lib/helpers/compatibility.helper';
import { useCompatibilityQuery, useRescanMutation } from '@/lib/queries/compatibility.queries';
import { LoadingButton } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { NotDetected } from '@/components/shared/NotDetected';
import { WarningBanner } from '@/components/shared/WarningBanner';
import PageLayout from '@/pages/PageLayout';
import { GatewaysSection } from './components/GatewaysSection';
import { PluginsSection } from './components/PluginsSection';
import { StoreSection } from './components/StoreSection';

export default function CompatibilityPage() {
  const query = useCompatibilityQuery();
  const rescan = useRescanMutation();
  // A failed first load still shows every section, each "Not detected"; Re-scan tries again.
  const report = query.data ?? (query.error ? FAILED_REPORT : undefined);

  return (
    <PageLayout
      title={__('Compatibility', 'yaycheckout')}
      description={__('What this store runs and how it fits the checkout YayCheckout renders.', 'yaycheckout')}
      actions={
        <LoadingButton
          variant="outline"
          loading={rescan.isPending}
          disabled={rescan.isPending || query.isPending}
          onClick={() => rescan.mutate()}
        >
          <RefreshCw />
          {__('Re-scan', 'yaycheckout')}
        </LoadingButton>
      }
    >
      {query.isPending && <CompatibilityPageSkeleton />}

      {report && (
        <>
          <WarningBanner warnings={deriveWarnings(report)} gatewayTotal={report.gateways?.length ?? 0} />
          {report.store ? (
            <StoreSection store={report.store} />
          ) : (
            <NotDetectedSection title={__('Store', 'yaycheckout')} />
          )}
          {report.gateways ? (
            <GatewaysSection gateways={report.gateways} />
          ) : (
            <NotDetectedSection title={__('Payment gateways', 'yaycheckout')} />
          )}
          {report.plugins ? (
            <PluginsSection plugins={report.plugins} />
          ) : (
            <NotDetectedSection title={__('Plugins touching checkout', 'yaycheckout')} />
          )}
        </>
      )}
    </PageLayout>
  );
}

/** A section whose detection failed, under the same title its card has. */
function NotDetectedSection({ title }: { title: string }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent className="text-sm">
        <NotDetected />
      </CardContent>
    </Card>
  );
}

/** Mirrors the three section cards (Store, Payment gateways, Plugins) so the page keeps its shape while the scan runs. */
function CompatibilityPageSkeleton() {
  return (
    <>
      <Card>
        <CardHeader>
          <Skeleton className="h-4 w-24" />
        </CardHeader>
        <CardContent className="space-y-3">
          {[0, 1, 2, 3, 4].map((i) => (
            <div key={i} className="flex gap-8">
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-3 w-48" />
            </div>
          ))}
        </CardContent>
      </Card>
      {[0, 1].map((card) => (
        <Card key={card}>
          <CardHeader>
            <Skeleton className="h-4 w-40" />
            <Skeleton className="mt-1 h-3 w-24" />
          </CardHeader>
          <CardContent className="space-y-3">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-10 w-full" />
            ))}
          </CardContent>
        </Card>
      ))}
    </>
  );
}

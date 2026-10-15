import { RefreshCw } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { deriveWarnings } from '@/lib/helpers/compatibility.helper';
import { formatDateTime } from '@/lib/helpers/date.helper';
import { useCompatibilityQuery, useRescanMutation } from '@/lib/queries/compatibility.queries';
import { LoadingButton } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { WarningBanner } from '@/components/shared/WarningBanner';
import PageLayout from '@/pages/PageLayout';
import { GatewaysSection } from './components/GatewaysSection';
import { PluginsSection } from './components/PluginsSection';
import { StoreSection } from './components/StoreSection';

export default function CompatibilityPage() {
  const query = useCompatibilityQuery();
  const rescan = useRescanMutation();
  const report = query.data;

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
      {query.error && <p className="text-destructive text-sm">{query.error.message}</p>}

      {report && (
        <>
          <WarningBanner warnings={deriveWarnings(report)} gatewayTotal={report.gateways.length} />
          <StoreSection store={report.store} />
          <GatewaysSection gateways={report.gateways} />
          <PluginsSection plugins={report.plugins} />
          <p className="text-muted-foreground text-xs">
            {report.catalogue.source === 'bundled'
              ? __("Compatibility list: built-in (couldn't reach yaycommerce.com)", 'yaycheckout')
              : sprintf(
                  /* translators: %s: date and time */
                  __('Compatibility list updated %s', 'yaycheckout'),
                  formatDateTime(report.catalogue.fetchedAt),
                )}
          </p>
        </>
      )}
    </PageLayout>
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

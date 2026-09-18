import { RefreshCw } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { formatDateTime } from '@/lib/helpers/date.helper';
import { useCompatibilityQuery, useRescanMutation } from '@/lib/queries/compatibility.queries';
import { LoadingButton } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import PageLayout from '@/pages/PageLayout';
import { deriveWarnings } from './compatibility-warnings';
import { GatewaysSection } from './components/GatewaysSection';
import { PluginsSection } from './components/PluginsSection';
import { StoreSection } from './components/StoreSection';
import { WarningBanner } from './components/WarningBanner';

export default function CompatibilityPage() {
  const query = useCompatibilityQuery();
  const rescan = useRescanMutation();
  const report = query.data;

  return (
    <PageLayout
      heading={__('Compatibility', 'yaycheckout')}
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

function CompatibilityPageSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-32 w-full" />
      <Skeleton className="h-48 w-full" />
      <Skeleton className="h-40 w-full" />
    </div>
  );
}

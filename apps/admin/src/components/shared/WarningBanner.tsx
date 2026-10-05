import { TriangleAlert } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import type { CompatibilityWarnings } from '@/lib/helpers/compatibility.helper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/** One muted alert per warning kind; rendered only when there is something to say. */
export function WarningBanner({ warnings, gatewayTotal }: { warnings: CompatibilityWarnings; gatewayTotal: number }) {
  const { blockOnly, unverifiedPlugins } = warnings;
  if (blockOnly.length === 0 && unverifiedPlugins.length === 0) return null;

  return (
    <div className="space-y-3">
      {blockOnly.length > 0 && (
        <Alert variant="muted">
          <TriangleAlert />
          <AlertTitle>
            {sprintf(
              /* translators: %s: comma-separated gateway titles */
              _n(
                'A block-only integration was detected: %s',
                'Block-only integrations were detected: %s',
                blockOnly.length,
                'yaycheckout',
              ),
              blockOnly.map((gateway) => gateway.title).join(', '),
            )}
          </AlertTitle>
          <AlertDescription>
            {sprintf(
              /* translators: 1: number of block-only gateways, 2: total gateways */
              __('%1$d of %2$d gateways', 'yaycheckout'),
              blockOnly.length,
              gatewayTotal,
            )}
          </AlertDescription>
        </Alert>
      )}
      {unverifiedPlugins.length > 0 && (
        <Alert variant="muted">
          <TriangleAlert />
          <AlertTitle>
            {sprintf(
              /* translators: %d: number of plugins */
              _n(
                '%d plugin touching checkout is not on our tested list',
                '%d plugins touching checkout are not on our tested list',
                unverifiedPlugins.length,
                'yaycheckout',
              ),
              unverifiedPlugins.length,
            )}
          </AlertTitle>
          <AlertDescription>{unverifiedPlugins.map((plugin) => plugin.name).join(', ')}</AlertDescription>
        </Alert>
      )}
    </div>
  );
}

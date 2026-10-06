import { Puzzle } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { useIntegrations } from '@/lib/queries/settings.queries';
import type { Integration } from '@/lib/schema/settings.schema';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { DocLink } from './DocLink';

export function IntegrationsTab() {
  const integrations = useIntegrations();
  const detected = integrations.filter((integration) => integration.detected);
  const missing = integrations.filter((integration) => !integration.detected);

  if (detected.length === 0) {
    return (
      <div className="flex flex-col gap-4 p-6">
        <Empty className="items-start p-0 text-left">
          <EmptyHeader className="items-start">
            <EmptyMedia variant="icon">
              <Puzzle />
            </EmptyMedia>
            <EmptyTitle>{__('No Yay plugins detected', 'yaycheckout')}</EmptyTitle>
            <EmptyDescription className="max-w-xl text-left">
              {__(
                'YayCheckout works on its own. If you install one of these, its blocks appear in the editor and its settings appear here.',
                'yaycheckout',
              )}
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
        <div className="grid gap-3 sm:grid-cols-2">
          {missing.map((integration) => (
            <CompanionCard key={integration.slug} integration={integration} />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3 p-4">
      {detected.map((integration) => (
        <DetectedCard key={integration.slug} integration={integration} />
      ))}
      {missing.map((integration) => (
        <CompanionCard key={integration.slug} integration={integration} muted />
      ))}
    </div>
  );
}

function DetectedCard({ integration }: { integration: Integration }) {
  return (
    <div className="flex items-center gap-3 rounded-md border p-4">
      <div className="flex flex-1 flex-col gap-0.5">
        <div className="text-sm font-semibold">{integration.name}</div>
        <div className="text-muted-foreground font-mono text-xs">
          {integration.version}
          {' · '}
          {integration.active ? __('active', 'yaycheckout') : __('inactive', 'yaycheckout')}
        </div>
      </div>
      <Badge variant="outline">{__('Detected', 'yaycheckout')}</Badge>
    </div>
  );
}

function CompanionCard({ integration, muted = false }: { integration: Integration; muted?: boolean }) {
  return (
    <div className={cn('flex flex-col gap-1 rounded-md border p-4', muted && 'opacity-60')}>
      <div className="text-sm font-semibold">
        {integration.name}
        {muted ? (
          <span className="text-muted-foreground font-normal"> — {__('not installed', 'yaycheckout')}</span>
        ) : null}
      </div>
      <div className="text-muted-foreground text-xs/relaxed">{integration.description}</div>
      {integration.learnMoreUrl ? (
        <DocLink href={integration.learnMoreUrl} className="mt-1 text-xs">
          {__('Learn more', 'yaycheckout')}
        </DocLink>
      ) : null}
    </div>
  );
}

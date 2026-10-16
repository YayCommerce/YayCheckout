import { ExternalLink, Puzzle } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { useIntegrations } from '@/lib/queries/settings.queries';
import type { Integration } from '@/lib/schema/settings.schema';
import { Badge } from '@/components/ui/badge';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { DocLink } from './DocLink';
import { SettingsSection } from './SettingsSection';

const TILE_GRID_CLASS = 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3';

export function IntegrationsTab() {
  const integrations = useIntegrations();
  const detected = integrations.filter((integration) => integration.detected);
  const missing = integrations.filter((integration) => !integration.detected);

  return (
    <>
      <SettingsSection
        title={__('Detected plugins', 'yaycheckout')}
        description={__('Yay plugins on this store that add blocks to the editor.', 'yaycheckout')}
      >
        {detected.length === 0 ? (
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
        ) : (
          <div className={TILE_GRID_CLASS}>
            {detected.map((integration) => (
              <IntegrationTile key={integration.slug} integration={integration} />
            ))}
          </div>
        )}
      </SettingsSection>

      {missing.length > 0 ? (
        <SettingsSection
          title={__('Works with YayCheckout', 'yaycheckout')}
          description={__('Plugins you can add for more checkout blocks.', 'yaycheckout')}
        >
          <div className={TILE_GRID_CLASS}>
            {missing.map((integration) => (
              <IntegrationTile key={integration.slug} integration={integration} />
            ))}
          </div>
        </SettingsSection>
      ) : null}
    </>
  );
}

/** "YayLoyalty" → "YL": the capitals of the name, for a plugin with no icon. */
function initials(name: string): string {
  const capitals = name.match(/[A-Z]/g)?.join('') ?? '';
  return (capitals || name.charAt(0)).slice(0, 2).toUpperCase();
}

function IntegrationTile({ integration }: { integration: Integration }) {
  let status = null;
  if (integration.detected) {
    status = integration.active ? (
      <Badge variant="success-soft">{__('Active', 'yaycheckout')}</Badge>
    ) : (
      <Badge variant="dimmed">{__('Inactive', 'yaycheckout')}</Badge>
    );
  }

  return (
    <div className="bg-card flex flex-col rounded-lg border">
      <div className="flex flex-1 flex-col gap-3 p-4">
        <div className="flex items-center gap-3">
          {integration.iconUrl ? (
            <img src={integration.iconUrl} alt="" className="size-10 shrink-0 rounded-md" />
          ) : (
            <div className="bg-primary/6 text-primary flex size-10 shrink-0 items-center justify-center rounded-md text-sm font-semibold">
              {initials(integration.name)}
            </div>
          )}
          <div className="text-sm font-semibold">{integration.name}</div>
          {status ? <div className="ml-auto">{status}</div> : null}
        </div>
        <p className="text-muted-foreground text-xs/relaxed">{integration.description}</p>
      </div>

      <div className="flex items-center justify-between gap-3 border-t px-4 py-3">
        {integration.detected && integration.version ? (
          <span className="text-muted-foreground font-mono text-xs">
            {
              /* translators: %s: plugin version */
              sprintf(__('v%s', 'yaycheckout'), integration.version)
            }
          </span>
        ) : (
          <span className="text-muted-foreground text-xs">{__('Not installed', 'yaycheckout')}</span>
        )}
        {integration.learnMoreUrl ? (
          <DocLink href={integration.learnMoreUrl} className="inline-flex items-center gap-1 text-xs">
            {__('Learn more', 'yaycheckout')}
            <ExternalLink className="size-3" />
          </DocLink>
        ) : null}
      </div>
    </div>
  );
}

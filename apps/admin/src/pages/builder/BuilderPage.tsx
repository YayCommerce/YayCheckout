import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useLayoutsQuery, usePublishLayoutMutation } from '@/lib/queries/layout.queries';
import { Button } from '@/components/ui/button';
import { ProGate } from '@/components/pro/ProGate';
import PagePlaceholder from '@/pages/PagePlaceholder';

/**
 * Minimal wiring over the layout endpoints, not the builder UI. It exists to
 * prove the REST path end to end; the visual builder replaces it.
 */
export default function BuilderPage() {
  const { data: layouts, isPending, error } = useLayoutsQuery();
  const publish = usePublishLayoutMutation();

  return (
    <PagePlaceholder
      title={__('Builder', 'yaycheckout')}
      description={__('Visually arrange the checkout layout, sections and steps.', 'yaycheckout')}
    >
      {isPending && <p>{__('Loading layouts…', 'yaycheckout')}</p>}
      {error && <p>{error.message}</p>}

      {layouts && (
        <p className="mb-3 flex items-center gap-2">
          {/* The limit itself is server-side; showing the count here avoids
              duplicating it in TS where it could drift out of step. */}
          <span>
            {layouts.length} {__('layouts', 'yaycheckout')}
          </span>
          {!isPro && <ProGate inline />}
        </p>
      )}

      {layouts && layouts.length === 0 && <p>{__('No layouts yet.', 'yaycheckout')}</p>}

      {layouts && layouts.length > 0 && (
        <ul className="flex flex-col gap-2">
          {layouts.map((layout) => (
            <li key={layout.id} className="flex items-center gap-3">
              <span className="grow">
                {layout.title}
                {layout.isActive && ` — ${__('active', 'yaycheckout')}`}
              </span>
              <span className="text-muted-foreground">
                {layout.publishedAt ?? __('never published', 'yaycheckout')}
              </span>
              <Button size="sm" disabled={publish.isPending} onClick={() => publish.mutate(layout.id)}>
                {__('Publish', 'yaycheckout')}
              </Button>
            </li>
          ))}
        </ul>
      )}
    </PagePlaceholder>
  );
}

import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useLayoutsQuery, usePublishLayoutMutation } from '@/lib/queries/layout.queries';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { ProGate } from '@/components/pro/ProGate';
import PageLayout from '@/pages/PageLayout';

/**
 * Minimal wiring over the layout endpoints, not the builder UI. It exists to
 * prove the REST path end to end; the visual builder replaces it.
 *
 * The right rail (Health, Compatibility) is a Layouts-only concern, so it is
 * laid out here rather than as a PageLayout prop. Both boxes are shells until
 * their data sources exist (analytics table in 1.3; the Compatibility screen).
 */
export default function LayoutsPage() {
  const { data, isPending, error } = useLayoutsQuery();
  const publish = usePublishLayoutMutation();
  const layouts = data?.items;

  return (
    <PageLayout
      heading={__('Layouts', 'yaycheckout')}
      description={__('Create, publish and activate checkout layouts.', 'yaycheckout')}
      actions={<Button size="sm">{__('Add layout', 'yaycheckout')}</Button>}
    >
      <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
        <div className="space-y-6">
          <Card>
            {isPending && <p>{__('Loading layouts…', 'yaycheckout')}</p>}
            {error && <p>{error.message}</p>}

            {data && (
              <p className="flex items-center gap-2">
                {/* The limit itself is server-side; showing the count here avoids
                    duplicating it in TS where it could drift out of step. */}
                <span>
                  {data.counts.listed} {__('layouts', 'yaycheckout')}
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
          </Card>
        </div>

        <aside className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{__('Health', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <p className="text-muted-foreground text-sm">{__('No data until a layout is live.', 'yaycheckout')}</p>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{__('Compatibility', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <p className="text-muted-foreground text-sm">
              {__('No compatibility checks have run yet.', 'yaycheckout')}
            </p>
          </Card>
        </aside>
      </div>
    </PageLayout>
  );
}

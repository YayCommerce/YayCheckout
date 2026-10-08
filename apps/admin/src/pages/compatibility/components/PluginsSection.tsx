import { __, sprintf } from '@wordpress/i18n';

import type { CheckoutTouch, OwnerKind, PluginRow } from '@/lib/schema/compatibility.schema';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/shared/StatusBadge';

const TOUCH_LABELS: Record<CheckoutTouch, string> = {
  fields: __('fields', 'yaycheckout'),
  templates: __('templates', 'yaycheckout'),
  gateways: __('gateways', 'yaycheckout'),
  order_review: __('order review', 'yaycheckout'),
  submission: __('submission', 'yaycheckout'),
};

const KIND_HINTS: Record<OwnerKind, string> = {
  plugin: '',
  'mu-plugin': __('mu-plugin', 'yaycheckout'),
  theme: __('theme', 'yaycheckout'),
};

export function PluginsSection({ plugins }: { plugins: PluginRow[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Plugins touching checkout', 'yaycheckout')}</CardTitle>
        <CardDescription>
          {plugins.length === 0
            ? __('none detected', 'yaycheckout')
            : sprintf(
                /* translators: %d: number of plugins */
                __('%d detected', 'yaycheckout'),
                plugins.length,
              )}
        </CardDescription>
      </CardHeader>
      <CardContent>
        {plugins.length === 0 ? (
          <Empty className="py-8">
            <EmptyHeader>
              <EmptyTitle>{__('None detected', 'yaycheckout')}</EmptyTitle>
              <EmptyDescription>
                {__('No other plugin filters checkout fields, gateways or templates.', 'yaycheckout')}
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{__('Plugin', 'yaycheckout')}</TableHead>
                <TableHead>{__('Status', 'yaycheckout')}</TableHead>
                <TableHead>{__('Touches', 'yaycheckout')}</TableHead>
                <TableHead>{__('Notes', 'yaycheckout')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {plugins.map((plugin) => {
                const hint = KIND_HINTS[plugin.kind];
                return (
                  <TableRow key={`${plugin.kind}:${plugin.slug}`}>
                    <TableCell className="whitespace-normal">
                      <span className="font-medium">{plugin.name}</span>
                      <span className="text-muted-foreground ml-2 text-xs">
                        {[plugin.version, hint].filter(Boolean).join(' · ')}
                      </span>
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={plugin.status} />
                    </TableCell>
                    <TableCell>{plugin.touches.map((touch) => TOUCH_LABELS[touch]).join(', ')}</TableCell>
                    <TableCell className="text-muted-foreground whitespace-normal">{plugin.notes ?? ''}</TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}

import { TriangleAlert } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import { useLayoutCheckQuery } from '@/lib/queries/layout.queries';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { LayoutConfirmDialog, type LayoutConfirmProps } from '@/pages/layouts/LayoutConfirmDialog';

export function SetActiveModal(props: LayoutConfirmProps) {
  return (
    <LayoutConfirmDialog
      {...props}
      title={(layout) =>
        sprintf(
          /* translators: %s: layout title */
          __('Make "%s" your live checkout?', 'yaycheckout'),
          layout.title,
        )
      }
      body={(layout) => (
        <>
          <p className="text-muted-foreground text-sm">
            {__('Shoppers will start seeing this layout as soon as you confirm.', 'yaycheckout')}
          </p>
          <CheckSummary id={layout.id} />
        </>
      )}
      confirmLabel={__('Activate', 'yaycheckout')}
      pendingLabel={__('Activating…', 'yaycheckout')}
    />
  );
}

/**
 * Run test's count, so a merchant hears about problems before shoppers do. It never blocks: what blocks
 * Activate is LayoutMarkupReader::publish_error(), which the server answers on confirm.
 */
function CheckSummary({ id }: { id: number }) {
  const { data } = useLayoutCheckQuery(id);
  const count = (data ?? []).filter((finding) => finding.severity !== 'info').length;

  if (count === 0) {
    return null;
  }

  return (
    <Alert variant="outline" className="border-warning/30 bg-warning/5">
      <TriangleAlert />
      <AlertDescription>
        {sprintf(
          /* translators: %d: number of problems Run test found */
          _n(
            'Run test found %d problem in this layout. Open it in the editor and use Run test to see it.',
            'Run test found %d problems in this layout. Open it in the editor and use Run test to see them.',
            count,
            'yaycheckout',
          ),
          count,
        )}
      </AlertDescription>
    </Alert>
  );
}

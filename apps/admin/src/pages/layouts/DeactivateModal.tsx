import { TriangleAlert } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export function DeactivateModal({
  layout,
  isLastActive,
  onOpenChange,
  onConfirm,
  isPending,
}: {
  layout: LayoutSummary | null;
  /** No layout would remain active. */
  isLastActive: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: (layout: LayoutSummary) => void;
  isPending: boolean;
}) {
  return (
    <Dialog open={null !== layout} onOpenChange={onOpenChange}>
      <DialogContent>
        {layout && (
          <>
            <DialogHeader>
              <DialogTitle>
                {sprintf(
                  /* translators: %s: layout title */
                  __('Deactivate "%s"?', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-muted-foreground text-sm">
              {__(
                'Shoppers will stop seeing this layout. It stays published, so you can activate it again at any time.',
                'yaycheckout',
              )}
            </p>

            {isLastActive && (
              <Alert variant="muted" destructive>
                <TriangleAlert />
                <AlertDescription>
                  {__(
                    'No layout will be active. Your checkout page falls back to the default WooCommerce checkout.',
                    'yaycheckout',
                  )}
                </AlertDescription>
              </Alert>
            )}

            <DialogFooter>
              <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <Button variant="destructive" onClick={() => onConfirm(layout)} disabled={isPending}>
                {isPending ? __('Deactivating…', 'yaycheckout') : __('Deactivate', 'yaycheckout')}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

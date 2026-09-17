import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Confirms activating a layout. A layout that has never been published is
 * published by the same request, which is why the submit button says so.
 */
export function SetActiveModal({
  layout,
  onOpenChange,
  onConfirm,
  isPending,
}: {
  layout: LayoutSummary | null;
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
                  __('Make "%s" your live checkout?', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-muted-foreground text-sm">
              {__('Shoppers will start seeing this layout as soon as you confirm.', 'yaycheckout')}
            </p>

            <DialogFooter>
              <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <Button onClick={() => onConfirm(layout)} disabled={isPending}>
                {/* Anything not already published gets published by the same
                 * request — pending and scheduled included, so the label has to
                 * say so rather than singling out drafts. */}
                {isPending
                  ? __('Activating…', 'yaycheckout')
                  : layout.status === 'published'
                    ? __('Activate', 'yaycheckout')
                    : __('Publish & activate', 'yaycheckout')}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

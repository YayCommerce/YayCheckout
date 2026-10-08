import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

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
                {isPending ? __('Activating…', 'yaycheckout') : __('Activate', 'yaycheckout')}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

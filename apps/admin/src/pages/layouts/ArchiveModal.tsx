import { Info } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Confirms moving a layout to the archive. Reversible, so the confirm button
 * is not destructive. Never opens for the active row — its Archive item is
 * disabled, matching LayoutActiveGuard.
 */
export function ArchiveModal({
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
                  __('Archive "%s"?', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-muted-foreground text-sm">
              {sprintf(
                /* translators: %s: layout title */
                __(
                  '"%s" moves out of the list into the Archive tab. Its blocks and revisions are kept.',
                  'yaycheckout',
                ),
                layout.title,
              )}
            </p>

            <Alert variant="muted">
              <Info />
              <AlertDescription>
                {__('Restore it any time from the Archive tab. Archiving is not deleting.', 'yaycheckout')}
              </AlertDescription>
            </Alert>

            <DialogFooter>
              <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <Button onClick={() => onConfirm(layout)} disabled={isPending}>
                {isPending ? __('Archiving…', 'yaycheckout') : __('Archive', 'yaycheckout')}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

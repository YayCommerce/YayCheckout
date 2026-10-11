import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export type LayoutConfirmProps = {
  layout: LayoutSummary | null;
  onOpenChange: (open: boolean) => void;
  onConfirm: (layout: LayoutSummary) => void;
  isPending: boolean;
};

/** A row action's confirm step: open while `layout` is set, a title and body about it, then Cancel and the action. */
export function LayoutConfirmDialog({
  layout,
  onOpenChange,
  onConfirm,
  isPending,
  title,
  body,
  confirmLabel,
  pendingLabel,
  destructive = false,
}: LayoutConfirmProps & {
  title: (layout: LayoutSummary) => string;
  /** The text under the title, then any alert. */
  body: (layout: LayoutSummary) => ReactNode;
  confirmLabel: string;
  pendingLabel: string;
  destructive?: boolean;
}) {
  return (
    <Dialog open={null !== layout} onOpenChange={onOpenChange}>
      <DialogContent>
        {layout && (
          <>
            <DialogHeader>
              <DialogTitle>{title(layout)}</DialogTitle>
            </DialogHeader>

            {body(layout)}

            <DialogFooter>
              <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <Button
                variant={destructive ? 'destructive' : undefined}
                onClick={() => onConfirm(layout)}
                disabled={isPending}
              >
                {isPending ? pendingLabel : confirmLabel}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

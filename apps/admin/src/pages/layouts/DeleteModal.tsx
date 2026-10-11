import { TriangleAlert } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { useTypedConfirm } from '@/hooks/useTypedConfirm';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TypedConfirmField } from '@/components/shared/TypedConfirmField';

const CONFIRM_PHRASE = 'DELETE';

/** Confirms permanently deleting an archived layout. It cannot be undone, so it is gated by a typed phrase. */
export function DeleteModal({
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
  const { typed, setTyped, confirmed: canDelete } = useTypedConfirm(CONFIRM_PHRASE, layout?.id, isPending);

  return (
    <Dialog open={null !== layout} onOpenChange={(open) => !isPending && onOpenChange(open)}>
      <DialogContent>
        {layout && (
          <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (canDelete) onConfirm(layout);
            }}
          >
            <DialogHeader>
              <DialogTitle>
                {sprintf(
                  /* translators: %s: layout title */
                  __('Delete "%s" permanently?', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-destructive flex items-start gap-2 text-sm">
              <TriangleAlert className="mt-0.5 size-4 shrink-0" />
              {sprintf(
                /* translators: %s: layout title */
                __('This permanently removes "%s". This cannot be undone.', 'yaycheckout'),
                layout.title,
              )}
            </p>

            <TypedConfirmField
              id="delete-layout-confirm"
              label={__('Type DELETE to confirm', 'yaycheckout')}
              phrase={CONFIRM_PHRASE}
              className="font-mono"
              value={typed}
              onChange={setTyped}
              disabled={isPending}
            />

            <DialogFooter className="sm:justify-between">
              <p className="text-muted-foreground self-center text-xs">
                {__('Only archived, non-active layouts can be deleted.', 'yaycheckout')}
              </p>
              <div className="flex gap-2">
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                  {__('Cancel', 'yaycheckout')}
                </Button>
                <Button type="submit" variant="destructive" disabled={!canDelete}>
                  {isPending ? __('Deleting…', 'yaycheckout') : __('Delete permanently', 'yaycheckout')}
                </Button>
              </div>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

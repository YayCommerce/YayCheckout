import { useEffect, useState } from 'react';
import { TriangleAlert } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

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
  const [typed, setTyped] = useState('');

  useEffect(() => setTyped(''), [layout?.id]);

  const canDelete = typed.trim() === CONFIRM_PHRASE && !isPending;

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

            <Field>
              <FieldLabel htmlFor="delete-layout-confirm">{__('Type DELETE to confirm', 'yaycheckout')}</FieldLabel>
              <Input
                id="delete-layout-confirm"
                className="font-mono tracking-widest"
                placeholder={CONFIRM_PHRASE}
                autoComplete="off"
                autoFocus
                value={typed}
                onChange={(event) => setTyped(event.target.value)}
                disabled={isPending}
              />
            </Field>

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

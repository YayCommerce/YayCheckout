import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { __, _n, sprintf } from '@wordpress/i18n';

import { useLayoutQuery, useResetLayoutMutation } from '@/lib/queries/layout.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button, LoadingButton } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const CONFIRM_PHRASE = 'RESET';

/** Serialized markup opens every block with `<!-- wp:name`, one per block; closers start with `/wp:`. */
function countBlocks(content: string) {
  return content.match(/<!-- wp:yaycheckout\//g)?.length ?? 0;
}

/** Confirms wiping a layout back to the blank starting layout; gated by a typed phrase like DeleteModal. */
export function ResetModal({
  layout,
  onOpenChange,
}: {
  layout: LayoutSummary | null;
  onOpenChange: (open: boolean) => void;
}) {
  const [typed, setTyped] = useState('');
  const reset = useResetLayoutMutation();
  const detail = useLayoutQuery(layout?.id ?? 0);

  useEffect(() => setTyped(''), [layout?.id]);

  const canReset = typed.trim().toUpperCase() === CONFIRM_PHRASE;
  const blocks = detail.data ? countBlocks(detail.data.content) : null;

  return (
    <Dialog open={null !== layout} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        {layout && (
          <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (!canReset) return;
              reset.mutate(layout.id, {
                onSuccess: (updated) => {
                  toast.success(
                    sprintf(
                      /* translators: %s: layout title */
                      __('Layout "%s" reset.', 'yaycheckout'),
                      updated.title,
                    ),
                  );
                  onOpenChange(false);
                },
              });
            }}
          >
            <DialogHeader>
              <DialogTitle>
                {sprintf(
                  /* translators: %s: layout title */
                  __('Reset "%s" to the default layout?', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-sm">
              {__(
                "Every block you added is removed and replaced with root plus the required sections. Revisions are kept, so you can roll back from the editor's history.",
                'yaycheckout',
              )}
            </p>

            <dl className="bg-muted/30 space-y-1 rounded-md border px-4 py-3 text-sm">
              <div className="flex gap-2">
                <dt className="text-muted-foreground">{__('Removes:', 'yaycheckout')}</dt>
                <dd>
                  {blocks === null
                    ? '…'
                    : sprintf(
                        /* translators: %d: number of blocks */
                        _n('%d block', '%d blocks', blocks, 'yaycheckout'),
                        blocks,
                      )}
                </dd>
              </div>
              <div className="flex gap-2">
                <dt className="text-muted-foreground">{__('Keeps:', 'yaycheckout')}</dt>
                <dd>{__('Layout name · status · revisions', 'yaycheckout')}</dd>
              </div>
            </dl>

            <Field>
              <FieldLabel htmlFor="reset-layout-confirm">{__('Type RESET to confirm', 'yaycheckout')}</FieldLabel>
              <Input
                id="reset-layout-confirm"
                className="max-w-xs tracking-widest"
                placeholder={CONFIRM_PHRASE}
                autoComplete="off"
                autoFocus
                value={typed}
                onChange={(event) => setTyped(event.target.value)}
              />
            </Field>

            <DialogFooter className="border-t pt-4">
              <Button type="button" variant="outline" disabled={reset.isPending} onClick={() => onOpenChange(false)}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <LoadingButton type="submit" variant="destructive" disabled={!canReset} loading={reset.isPending}>
                {__('Reset layout', 'yaycheckout')}
              </LoadingButton>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

import { TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';
import { __, _n, sprintf } from '@wordpress/i18n';

import { useResetLayoutMutation } from '@/lib/queries/layout.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { useTypedConfirm } from '@/hooks/useTypedConfirm';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button, LoadingButton } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { HelpTip } from '@/components/ui/tooltip';
import { TypedConfirmField } from '@/components/shared/TypedConfirmField';

const CONFIRM_PHRASE = 'RESET';

/** Confirms resetting a layout to the default layout, gated by a typed phrase. */
export function ResetModal({
  layout,
  onOpenChange,
  onReset,
}: {
  layout: LayoutSummary | null;
  onOpenChange: (open: boolean) => void;
  /** The layout as reset, so the card's copy shows the new block count and size. */
  onReset: (layout: LayoutSummary) => void;
}) {
  const reset = useResetLayoutMutation();
  const { typed, setTyped, confirmed: canReset } = useTypedConfirm(CONFIRM_PHRASE, layout?.id, reset.isPending);

  return (
    <Dialog open={null !== layout} onOpenChange={(open) => !reset.isPending && onOpenChange(open)}>
      <DialogContent className="sm:max-w-2xl">
        {layout && (
          <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (!canReset) return;
              reset.mutate(layout.id, {
                onSuccess: (updated) => {
                  onReset(updated);
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
                  __('Reset "%s" to its template?', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-sm">
              {__(
                "Every block you added is removed and replaced with the layout's template. Your design changes for this layout will be lost. Revisions keep the old blocks, so you can roll back the blocks from the editor's history.",
                'yaycheckout',
              )}
            </p>

            <dl className="bg-muted/30 space-y-1 rounded-md border px-4 py-3 text-sm">
              <div className="flex gap-2">
                <dt className="text-muted-foreground">{__('Removes:', 'yaycheckout')}</dt>
                <dd className="flex items-center gap-1.5">
                  {sprintf(
                    /* translators: %d: number of YayCheckout blocks */
                    _n('%d checkout block', '%d checkout blocks', layout.blocks, 'yaycheckout'),
                    layout.blocks,
                  )}
                  {/* HelpTip, not YayCheckoutToolTip: its layer sits above the dialog's. */}
                  <HelpTip
                    className="max-w-64"
                    content={__(
                      'Only YayCheckout blocks are counted: sections, fields and slots. Other blocks are not included.',
                      'yaycheckout',
                    )}
                  />
                </dd>
              </div>
              <div className="flex gap-2">
                <dt className="text-muted-foreground">{__('Keeps:', 'yaycheckout')}</dt>
                <dd>{__('Layout name · status · revisions', 'yaycheckout')}</dd>
              </div>
            </dl>

            <TypedConfirmField
              id="reset-layout-confirm"
              label={__('Type RESET to confirm', 'yaycheckout')}
              phrase={CONFIRM_PHRASE}
              className="max-w-xs"
              value={typed}
              onChange={setTyped}
            />

            {layout.isActive && (
              // Tinted like the dialog's other boxes, so it does not read as loose text beside the footer rule.
              <Alert variant="outline" destructive className="border-destructive/30 bg-destructive/5 -mt-1">
                <TriangleAlert />
                <AlertDescription>
                  {__('This layout is active: shoppers see the change as soon as you reset it.', 'yaycheckout')}
                </AlertDescription>
              </Alert>
            )}

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

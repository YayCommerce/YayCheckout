import { Info, LayoutTemplate, TriangleAlert } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { useTypedConfirm } from '@/hooks/useTypedConfirm';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TypedConfirmField } from '@/components/shared/TypedConfirmField';
import { NoActiveLayoutAlert } from '@/pages/layouts/DeactivateModal';

/** The bulk actions that confirm first; Restore runs at once, as the row's does. */
export type ConfirmedBulkAction = 'deactivate' | 'archive' | 'delete';

const CONFIRM_PHRASE = 'DELETE';
const NAMES_SHOWN = 5;

/**
 * Confirms a bulk action on `layouts`, the selected rows it applies to. Open while `action` is set.
 * Delete is gated by a typed phrase, as DeleteModal is.
 */
export function BulkActionDialog({
  action,
  layouts,
  leavesNoneActive,
  onOpenChange,
  onConfirm,
  isPending,
}: {
  action: ConfirmedBulkAction | null;
  layouts: LayoutSummary[];
  /** Deactivate only: every active layout is in `layouts`. */
  leavesNoneActive: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
  isPending: boolean;
}) {
  const { typed, setTyped, confirmed } = useTypedConfirm(CONFIRM_PHRASE, action, isPending);
  const count = layouts.length;
  const canConfirm = action === 'delete' ? confirmed : !isPending;

  return (
    <Dialog open={null !== action} onOpenChange={(open) => !isPending && onOpenChange(open)}>
      <DialogContent>
        {action && (
          <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (canConfirm) onConfirm();
            }}
          >
            <DialogHeader>
              <DialogTitle>{title(action, count)}</DialogTitle>
            </DialogHeader>

            <LayoutNames layouts={layouts} />

            {action === 'deactivate' && (
              <>
                <p className="text-muted-foreground text-sm">
                  {__(
                    'Shoppers stop seeing the layouts listed. Each becomes inactive, and you can activate it again at any time.',
                    'yaycheckout',
                  )}
                </p>
                {leavesNoneActive && <NoActiveLayoutAlert />}
              </>
            )}

            {action === 'archive' && (
              <Alert variant="muted">
                <Info />
                <AlertDescription>
                  {__(
                    'Archived layouts move to the Archive tab with their blocks and revisions, and you can restore them any time. Archiving is not deleting.',
                    'yaycheckout',
                  )}
                </AlertDescription>
              </Alert>
            )}

            {action === 'delete' && (
              <>
                <p className="text-destructive flex items-start gap-2 text-sm">
                  <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                  {__('This permanently removes the layouts listed. This cannot be undone.', 'yaycheckout')}
                </p>
                <TypedConfirmField
                  id="bulk-delete-layouts-confirm"
                  label={__('Type DELETE to confirm', 'yaycheckout')}
                  phrase={CONFIRM_PHRASE}
                  className="font-mono"
                  value={typed}
                  onChange={setTyped}
                  disabled={isPending}
                />
              </>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <Button type="submit" variant="destructive" disabled={!canConfirm}>
                {isPending ? pendingLabel(action) : confirmLabel(action)}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

function LayoutNames({ layouts }: { layouts: LayoutSummary[] }) {
  const hidden = layouts.length - NAMES_SHOWN;

  return (
    <ul className="divide-border divide-y rounded-lg border text-sm">
      {layouts.slice(0, NAMES_SHOWN).map((layout) => (
        <li key={layout.id} className="flex items-center gap-2.5 px-3 py-2">
          <LayoutTemplate className="text-muted-foreground size-4 shrink-0" />
          <span className="truncate font-medium" title={layout.title}>
            {layout.title}
          </span>
        </li>
      ))}
      {hidden > 0 && (
        <li className="text-muted-foreground px-3 py-2">
          {sprintf(
            /* translators: %d: number of layouts not listed */
            _n('+%d more', '+%d more', hidden, 'yaycheckout'),
            hidden,
          )}
        </li>
      )}
    </ul>
  );
}

function title(action: ConfirmedBulkAction, count: number): string {
  switch (action) {
    case 'deactivate':
      /* translators: %d: number of layouts */
      return sprintf(_n('Deactivate %d layout?', 'Deactivate %d layouts?', count, 'yaycheckout'), count);
    case 'archive':
      /* translators: %d: number of layouts */
      return sprintf(_n('Archive %d layout?', 'Archive %d layouts?', count, 'yaycheckout'), count);
    case 'delete':
      /* translators: %d: number of layouts */
      return sprintf(
        _n('Delete %d layout permanently?', 'Delete %d layouts permanently?', count, 'yaycheckout'),
        count,
      );
  }
}

function confirmLabel(action: ConfirmedBulkAction): string {
  switch (action) {
    case 'deactivate':
      return __('Deactivate', 'yaycheckout');
    case 'archive':
      return __('Archive', 'yaycheckout');
    case 'delete':
      return __('Delete permanently', 'yaycheckout');
  }
}

function pendingLabel(action: ConfirmedBulkAction): string {
  switch (action) {
    case 'deactivate':
      return __('Deactivating…', 'yaycheckout');
    case 'archive':
      return __('Archiving…', 'yaycheckout');
    case 'delete':
      return __('Deleting…', 'yaycheckout');
  }
}

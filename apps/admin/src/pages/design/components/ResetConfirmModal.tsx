import { Info } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button, LoadingButton } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Confirms loading the Default preset over every token and saves immediately —
 * hence the destructive button, since this one cannot be undone by leaving unsaved.
 */
export function ResetConfirmModal({
  open,
  overrideCount,
  isPending,
  onOpenChange,
  onConfirm,
}: {
  open: boolean;
  overrideCount: number;
  isPending: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{__('Reset all tokens to theme defaults?', 'yaycheckout')}</DialogTitle>
        </DialogHeader>

        <p className="text-muted-foreground text-sm">
          {__(
            'Colours, typography, spacing, buttons and inputs go back to the Default preset and save immediately. Custom CSS is kept.',
            'yaycheckout',
          )}
        </p>

        <Alert variant="muted">
          <Info />
          <AlertDescription className="text-xs">
            {sprintf(
              /* translators: %d: number of tokens that differ from the Default preset */
              _n(
                '%d override will be cleared · custom CSS untouched',
                '%d overrides will be cleared · custom CSS untouched',
                overrideCount,
                'yaycheckout',
              ),
              overrideCount,
            )}
          </AlertDescription>
        </Alert>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <LoadingButton variant="destructive" loading={isPending} onClick={onConfirm}>
            {__('Reset', 'yaycheckout')}
          </LoadingButton>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

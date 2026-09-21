import { Info } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Confirms loading the Default preset over every token. Nothing is written until
 * Save, but the merchant cannot undo it after saving — hence the destructive button.
 */
export function ResetConfirmModal({
  open,
  overrideCount,
  onOpenChange,
  onConfirm,
}: {
  open: boolean;
  overrideCount: number;
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
            'Colours, typography, spacing, buttons and inputs go back to the Default preset. Custom CSS is kept.',
            'yaycheckout',
          )}
        </p>

        <Alert variant="muted">
          <Info />
          <AlertDescription className="font-mono text-xs">
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
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button variant="destructive" onClick={onConfirm}>
            {__('Reset', 'yaycheckout')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

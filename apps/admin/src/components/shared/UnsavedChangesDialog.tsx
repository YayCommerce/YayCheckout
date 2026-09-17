import { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

/** Pairs with useRouteLeaveGuard. */
export function UnsavedChangesDialog({
  open,
  onDiscard,
  onSave,
  onOpenChange,
  children = (
    <DialogDescription>
      {__('You have unsaved changes. Do you want to save them before leaving?', 'yaycheckout')}
    </DialogDescription>
  ),
}: {
  open: boolean;
  onDiscard: () => void;
  onSave: () => void;
  onOpenChange: (open: boolean) => void;
  children?: ReactNode;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{__('Unsaved changes', 'yaycheckout')}</DialogTitle>
          {children}
        </DialogHeader>

        <DialogFooter>
          <Button variant="ghost" onClick={onDiscard}>
            {__('Discard changes', 'yaycheckout')}
          </Button>
          <Button onClick={onSave}>{__('Save changes', 'yaycheckout')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

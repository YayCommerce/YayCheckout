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

interface UnsavedChangeDialogProps {
  open: boolean;
  onDiscard: () => void;
  onSave: () => void;
  onOpenChange: (open: boolean) => void;
  children?: React.ReactNode;
}

export function UnsavedChangeDialog({
  open,
  onDiscard,
  onSave,
  onOpenChange,
  children = (
    <DialogDescription>
      {__('You have unsaved changes. Do you want to save them before leaving?', 'yaycheckout')}
    </DialogDescription>
  ),
}: UnsavedChangeDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="bw:max-w-md">
        <DialogHeader className="bw:border-b-0">
          <DialogTitle>{__('Unsaved Changes', 'yaycheckout')}</DialogTitle>
          {children}
        </DialogHeader>

        <DialogFooter>
          <Button variant="ghost" onClick={onDiscard}>
            {__('Discard Changes', 'yaycheckout')}
          </Button>
          <Button onClick={onSave}>{__('Save Changes', 'yaycheckout')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

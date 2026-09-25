import type { ReactElement, ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { Popover, PopoverClose, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

/**
 * Small "are you sure?" popover anchored to its trigger, for removals that are cheap to redo but annoying to lose.
 * The trigger must forward its ref (a Radix `asChild` child). Confirm closes the popover in the same click as
 * `onConfirm`, so a list that re-keys after the removal does not hand the open state to the next row.
 */
export function ConfirmPopover({
  trigger,
  message,
  confirmLabel,
  onConfirm,
  disabled = false,
  align = 'end',
}: {
  trigger: ReactElement;
  message: ReactNode;
  confirmLabel: string;
  onConfirm: () => void;
  /** Disables the confirm button, e.g. while the removal request is in flight. */
  disabled?: boolean;
  align?: 'start' | 'center' | 'end';
}) {
  return (
    <Popover>
      <PopoverTrigger asChild>{trigger}</PopoverTrigger>
      <PopoverContent align={align} className="flex w-auto max-w-72 flex-col gap-3 p-3">
        <p className="text-xs">{message}</p>
        <div className="flex justify-end gap-2">
          <PopoverClose asChild>
            <Button type="button" variant="outline" size="sm" className="h-7 px-2.5 text-xs">
              {__('Cancel', 'yaycheckout')}
            </Button>
          </PopoverClose>
          <PopoverClose asChild>
            <Button
              type="button"
              variant="destructive"
              size="sm"
              className="h-7 px-2.5 text-xs"
              disabled={disabled}
              onClick={onConfirm}
            >
              {confirmLabel}
            </Button>
          </PopoverClose>
        </div>
      </PopoverContent>
    </Popover>
  );
}

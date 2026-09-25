import { Trash2 } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button, type ButtonProps } from '@/components/ui/button';

/** Red trash icon button for removing a row, as in the UI kits' repeatable-row pattern. Pass an aria-label: it has no text. */
export function TrashButton({ className, ...props }: ButtonProps) {
  return (
    <Button variant="ghost" size="icon-sm" className={cn('text-destructive', className)} {...props}>
      <Trash2 className="size-3.5" />
    </Button>
  );
}

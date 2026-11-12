import { useEffect, useRef, useState } from 'react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { useLayoutPriorityQuery, useSaveLayoutPriorityMutation } from '@/lib/queries/layout.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button, LoadingButton } from '@/components/ui/button';
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { AssignmentChips } from '@/pages/layouts/assignment/AssignmentChips';

type Direction = 'up' | 'down';

/** Pro only: orders the active layouts, which the storefront tries top first. */
export function PrioritySheet({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader>
          <SheetTitle>{__('Priority', 'yaycheckout')}</SheetTitle>
          <SheetDescription>
            {__(
              'When several layouts match a shopper, the highest one wins. Newly activated layouts go to the top.',
              'yaycheckout',
            )}
          </SheetDescription>
        </SheetHeader>

        {open && <PriorityForm onSaved={() => onOpenChange(false)} />}
      </SheetContent>
    </Sheet>
  );
}

function PriorityForm({ onSaved }: { onSaved: () => void }) {
  const priority = useLayoutPriorityQuery();

  if (!priority.data) {
    return <PriorityPlaceholder isError={priority.isError} />;
  }

  // Keyed by the stored order, so a refetch after a refused save starts the list over from the server's.
  return (
    <PriorityEditor key={priority.data.map((layout) => layout.id).join(',')} saved={priority.data} onSaved={onSaved} />
  );
}

function PriorityPlaceholder({ isError }: { isError: boolean }) {
  if (isError) {
    return (
      <p className="text-destructive flex-1 p-5 text-sm">
        {__('The active layouts could not be loaded.', 'yaycheckout')}
      </p>
    );
  }

  return (
    <div className="flex-1 space-y-3 p-5">
      <Skeleton className="h-14 w-full" />
      <Skeleton className="h-14 w-full" />
    </div>
  );
}

function PriorityEditor({ saved, onSaved }: { saved: LayoutSummary[]; onSaved: () => void }) {
  const [order, setOrder] = useState(saved);
  const [moved, setMoved] = useState<{ id: number; direction: Direction } | null>(null);
  const buttons = useRef(new Map<string, HTMLButtonElement>());
  const save = useSaveLayoutPriorityMutation();

  const isDirty = order.some((layout, index) => layout.id !== saved[index]?.id);

  // Reordering moves the row's DOM node, which drops focus; a button disabled at the end hands it to its twin.
  useEffect(() => {
    if (!moved) return;

    const button = buttons.current.get(`${moved.id}-${moved.direction}`);
    const twin = buttons.current.get(`${moved.id}-${moved.direction === 'up' ? 'down' : 'up'}`);
    (button && !button.disabled ? button : twin)?.focus();
  }, [order, moved]);

  function move(index: number, direction: Direction) {
    const target = direction === 'up' ? index - 1 : index + 1;
    const next = [...order];
    [next[index], next[target]] = [next[target], next[index]];

    setOrder(next);
    setMoved({ id: order[index].id, direction });
  }

  function onSave() {
    save.mutate(
      order.map((layout) => layout.id),
      {
        onSuccess: () => {
          toast.success(__('Priority saved.', 'yaycheckout'));
          onSaved();
        },
      },
    );
  }

  return (
    <>
      <ol className="flex-1 space-y-2 overflow-y-auto p-5">
        {order.map((layout, index) => (
          <li key={layout.id} className="flex items-center gap-3 rounded-lg border px-4 py-3">
            <span className="text-muted-foreground w-5 shrink-0 text-sm tabular-nums">{index + 1}</span>
            <div className="min-w-0 flex-1 space-y-1">
              <p className="truncate text-sm font-medium">{layout.title}</p>
              <div className="text-xs">
                <AssignmentChips rules={layout.rules} />
              </div>
            </div>
            {(['up', 'down'] as const).map((direction) => (
              <Button
                key={direction}
                ref={(button) => {
                  if (button) buttons.current.set(`${layout.id}-${direction}`, button);
                  else buttons.current.delete(`${layout.id}-${direction}`);
                }}
                variant="outline"
                size="icon-sm"
                aria-label={sprintf(
                  direction === 'up'
                    ? /* translators: %s: layout title */ __('Move %s up', 'yaycheckout')
                    : /* translators: %s: layout title */ __('Move %s down', 'yaycheckout'),
                  layout.title,
                )}
                disabled={save.isPending || (direction === 'up' ? index === 0 : index === order.length - 1)}
                onClick={() => move(index, direction)}
              >
                {direction === 'up' ? <ArrowUp /> : <ArrowDown />}
              </Button>
            ))}
          </li>
        ))}
      </ol>

      <SheetFooter>
        <SheetClose asChild>
          <Button variant="outline">{__('Cancel', 'yaycheckout')}</Button>
        </SheetClose>
        <LoadingButton loading={save.isPending} disabled={!isDirty || save.isPending} onClick={onSave}>
          {__('Save priority', 'yaycheckout')}
        </LoadingButton>
      </SheetFooter>
    </>
  );
}

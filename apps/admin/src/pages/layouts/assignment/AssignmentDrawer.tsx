import { useState } from 'react';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useLayoutRulesQuery, useSaveLayoutRulesMutation } from '@/lib/queries/layout.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { Button, LoadingButton } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
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
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import {
  groupsFromWire,
  groupsToWire,
  isConditionComplete,
  newCondition,
  newId,
  type Group,
} from '@/pages/layouts/assignment/condition-types';
import { AddConditionMenu, ConditionGroup, OrDivider } from '@/pages/layouts/assignment/ConditionGroup';
import { LayoutStatusBadge } from '@/pages/layouts/LayoutStatusBadge';

/**
 * Right-hand drawer where a layout's assignment rules are edited, opened from the row's "⋯" menu.
 * Lite gets the same drawer locked: a greyed sample form under an upgrade card, and no rules request (no route).
 */
export function AssignmentDrawer({
  layout,
  onOpenChange,
}: {
  layout: LayoutSummary | null;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <Sheet open={null !== layout} onOpenChange={onOpenChange}>
      {/* Wider than the default sheet: the value column grows into selects and multi-selects per condition type. */}
      <SheetContent className="sm:max-w-3xl">
        {/* Keyed so each opened layout starts from a fresh form. */}
        {layout &&
          (isPro ? (
            <AssignmentForm key={layout.id} layout={layout} onSaved={() => onOpenChange(false)} />
          ) : (
            <LockedAssignment layout={layout} />
          ))}
      </SheetContent>
    </Sheet>
  );
}

function AssignmentHeader({ layout }: { layout: LayoutSummary }) {
  return (
    <SheetHeader>
      <div className="flex items-center gap-2">
        <SheetTitle>{__('Assignment', 'yaycheckout')}</SheetTitle>
        {!isPro && <ProTag />}
      </div>
      <div className="flex items-center gap-2">
        <SheetDescription className="truncate">{layout.title}</SheetDescription>
        <span className="text-muted-foreground" aria-hidden>
          ·
        </span>
        <LayoutStatusBadge layout={layout} />
      </div>
    </SheetHeader>
  );
}

function AssignmentForm({ layout, onSaved }: { layout: LayoutSummary; onSaved: () => void }) {
  const rules = useLayoutRulesQuery(layout.id);

  return (
    <>
      <AssignmentHeader layout={layout} />

      {rules.data ? (
        <RulesEditor
          layoutId={layout.id}
          saved={groupsFromWire(rules.data.rules)}
          productNames={rules.data.labels.products}
          onSaved={onSaved}
        />
      ) : (
        <RulesPlaceholder isError={rules.isError} />
      )}
    </>
  );
}

/** Stands in for the editor until the stored rules arrive. */
function RulesPlaceholder({ isError }: { isError: boolean }) {
  if (isError) {
    return <p className="text-destructive flex-1 p-5 text-sm">{__('The rules could not be loaded.', 'yaycheckout')}</p>;
  }

  return (
    <div className="flex-1 space-y-3 p-5">
      <Skeleton className="h-4 w-24" />
      <Skeleton className="h-28 w-full" />
    </div>
  );
}

/** Mounted once the stored rules are in, so they seed the form exactly once and stay the "unchanged" baseline. */
function RulesEditor({
  layoutId,
  saved,
  productNames,
  onSaved,
}: {
  layoutId: number;
  saved: Group[];
  /** Names of the products in the saved rules, from the rules reply. */
  productNames: Record<string, string>;
  onSaved: () => void;
}) {
  const [groups, setGroups] = useState<Group[]>(saved);
  const save = useSaveLayoutRulesMutation();

  // Compared as wire rules: form ids differ on every seed.
  const isDirty = JSON.stringify(groupsToWire(groups)) !== JSON.stringify(groupsToWire(saved));
  // Every condition filled: a half-filled one would save a rule that tests nothing. No groups is valid: it clears the rules.
  const canSave = isDirty && groups.every((group) => group.conditions.every(isConditionComplete));

  function updateGroup(group: Group) {
    // A group emptied of its last condition goes with it.
    if (group.conditions.length === 0) {
      setGroups(groups.filter((other) => other.id !== group.id));
      return;
    }

    setGroups(groups.map((other) => (other.id === group.id ? group : other)));
  }

  function onSave() {
    save.mutate(
      { id: layoutId, rules: groupsToWire(groups) },
      {
        onSuccess: () => {
          toast.success(__('Rules saved.', 'yaycheckout'));
          onSaved();
        },
      },
    );
  }

  return (
    <>
      <div className="flex-1 space-y-3 overflow-y-auto p-5">
        <h3 className="text-muted-foreground mb-4 text-xs font-medium tracking-wider uppercase">
          {__('Conditions', 'yaycheckout')}
        </h3>

        {groups.length === 0 ? (
          <Empty className="bg-muted/50 items-start border text-left">
            <EmptyHeader className="items-start">
              <EmptyTitle>{__('No rules yet', 'yaycheckout')}</EmptyTitle>
              <EmptyDescription>
                {__('Add a condition to decide which shoppers see this layout.', 'yaycheckout')}
              </EmptyDescription>
            </EmptyHeader>
            <EmptyContent className="items-start">
              <AddConditionMenu onAdd={(type) => setGroups([{ id: newId(), conditions: [newCondition(type)] }])} />
            </EmptyContent>
          </Empty>
        ) : (
          groups.map((group, groupIndex) => (
            <div key={group.id} className="space-y-3">
              {groupIndex > 0 && <OrDivider />}
              <ConditionGroup
                index={groupIndex}
                group={group}
                onChange={updateGroup}
                onRemove={() => setGroups(groups.filter((other) => other.id !== group.id))}
                productNames={productNames}
              />
            </div>
          ))
        )}

        {groups.length > 0 && (
          <div className="flex justify-center">
            <Button onClick={() => setGroups([...groups, { id: newId(), conditions: [newCondition('country')] }])}>
              {__('Add group', 'yaycheckout')}
            </Button>
          </div>
        )}
      </div>

      <SheetFooter>
        <SheetClose asChild>
          <Button variant="outline">{__('Cancel', 'yaycheckout')}</Button>
        </SheetClose>
        <LoadingButton loading={save.isPending} disabled={!canSave || save.isPending} onClick={onSave}>
          {__('Save rules', 'yaycheckout')}
        </LoadingButton>
      </SheetFooter>
    </>
  );
}

/** Lite: the form greyed and inert, so what Pro adds shows in place, under the upgrade card. */
function LockedAssignment({ layout }: { layout: LayoutSummary }) {
  return (
    <>
      <AssignmentHeader layout={layout} />

      <div className="relative flex-1 p-5">
        <div aria-hidden className="pointer-events-none space-y-3 opacity-50 select-none">
          <h3 className="text-muted-foreground mb-4 text-xs font-medium tracking-wider uppercase">
            {__('Conditions', 'yaycheckout')}
          </h3>
          <div className="rounded-lg border border-dashed">
            <div className="text-muted-foreground border-b border-dashed px-4 py-2 text-xs">
              {sprintf(
                /* translators: %d: group number */
                __('Group %d — all of these must be true', 'yaycheckout'),
                1,
              )}
            </div>
            <div className="flex gap-2 p-4">
              {[__('Customer role', 'yaycheckout'), __('in list', 'yaycheckout'), __('Wholesale', 'yaycheckout')].map(
                (text, index) => (
                  <span key={text} className={cn('rounded-md border px-3 py-2 text-sm', index < 2 ? 'w-40' : 'flex-1')}>
                    {text}
                  </span>
                ),
              )}
            </div>
          </div>
        </div>

        <div className="bg-background absolute inset-x-5 top-40 space-y-3 rounded-lg border p-5 shadow-lg">
          <div className="flex items-center justify-between gap-2">
            <h3 className="text-base font-semibold">{__('Assignment rules', 'yaycheckout')}</h3>
            <ProTag />
          </div>
          <p className="text-sm">
            {__(
              'Send shoppers to different layouts by role, country, cart contents or device. On Lite, one layout is active for everyone.',
              'yaycheckout',
            )}
          </p>
          <div className="flex items-center gap-2 border-t pt-3">
            <p className="text-muted-foreground flex-1 text-xs">
              {__('Your rules are kept if you upgrade later.', 'yaycheckout')}
            </p>
            <SheetClose asChild>
              <Button variant="outline">{__('Not now', 'yaycheckout')}</Button>
            </SheetClose>
            <UpgradeButton size="default" variant="primary" />
          </div>
        </div>
      </div>

      <SheetFooter className="flex-row items-center justify-between">
        <p className="text-muted-foreground text-sm">{__('Rules are read-only on Lite.', 'yaycheckout')}</p>
        <SheetClose asChild>
          <Button variant="outline">{__('Close', 'yaycheckout')}</Button>
        </SheetClose>
      </SheetFooter>
    </>
  );
}

import { useState } from 'react';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { useLayoutRulesQuery, useSaveLayoutRulesMutation } from '@/lib/queries/layout.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
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

/** Right-hand drawer where a layout's assignment rules are edited. Opened from the row's "⋯" menu; Pro only. */
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
        {layout && <AssignmentForm key={layout.id} layout={layout} onSaved={() => onOpenChange(false)} />}
      </SheetContent>
    </Sheet>
  );
}

function AssignmentForm({ layout, onSaved }: { layout: LayoutSummary; onSaved: () => void }) {
  const rules = useLayoutRulesQuery(layout.id);

  return (
    <>
      <SheetHeader>
        <SheetTitle>{__('Assignment', 'yaycheckout')}</SheetTitle>
        <div className="flex items-center gap-2">
          <SheetDescription className="truncate">{layout.title}</SheetDescription>
          <span className="text-muted-foreground" aria-hidden>
            ·
          </span>
          <LayoutStatusBadge layout={layout} />
        </div>
      </SheetHeader>

      {rules.data ? (
        <RulesEditor
          layoutId={layout.id}
          saved={groupsFromWire(rules.data.rules)}
          productNames={rules.data.labels.products}
          onSaved={onSaved}
        />
      ) : (
        <div className="flex-1 space-y-3 p-5">
          {rules.isError ? (
            <p className="text-destructive text-sm">{__('The rules could not be loaded.', 'yaycheckout')}</p>
          ) : (
            <>
              <Skeleton className="h-4 w-24" />
              <Skeleton className="h-28 w-full" />
            </>
          )}
        </div>
      )}
    </>
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
  const dirty = JSON.stringify(groupsToWire(groups)) !== JSON.stringify(groupsToWire(saved));
  // Every condition filled: a half-filled one would save a rule that tests nothing. No groups is valid: it clears the rules.
  const canSave = dirty && groups.every((group) => group.conditions.every(isConditionComplete));

  function updateGroup(group: Group) {
    // A group emptied of its last condition goes with it.
    setGroups(
      group.conditions.length > 0
        ? groups.map((g) => (g.id === group.id ? group : g))
        : groups.filter((g) => g.id !== group.id),
    );
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
                onRemove={() => setGroups(groups.filter((g) => g.id !== group.id))}
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

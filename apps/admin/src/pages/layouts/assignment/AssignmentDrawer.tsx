import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
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
import { newCondition, type Group } from '@/pages/layouts/assignment/condition-types';
import { AddConditionMenu, ConditionGroup, OrDivider } from '@/pages/layouts/assignment/ConditionGroup';
import { LayoutStatusBadge } from '@/pages/layouts/LayoutStatusBadge';

/** Right-hand drawer where a layout's assignment rules are edited. Opened from the row's "⋯" menu. */
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
        {layout && <AssignmentForm key={layout.id} layout={layout} />}
      </SheetContent>
    </Sheet>
  );
}

function AssignmentForm({ layout }: { layout: LayoutSummary }) {
  const [groups, setGroups] = useState<Group[]>([]);

  function updateGroup(groupIndex: number, group: Group) {
    // A group emptied of its last condition goes with it.
    setGroups(
      group.length > 0
        ? groups.map((g, i) => (i === groupIndex ? group : g))
        : groups.filter((_, i) => i !== groupIndex),
    );
  }

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

      <div className="flex-1 space-y-3 overflow-y-auto p-5">
        <h3 className="text-muted-foreground mb-4 text-xs font-medium tracking-wider uppercase">
          {__('Conditions', 'yaycheckout')}
        </h3>

        {groups.length === 0 ? (
          <Empty className="bg-muted/50 items-start border text-left">
            <EmptyHeader className="items-start">
              <EmptyTitle>{__('No rules yet', 'yaycheckout')}</EmptyTitle>
              <EmptyDescription>
                {__(
                  'Without a rule this layout is never served, even once published. Add a condition to decide who sees it.',
                  'yaycheckout',
                )}
              </EmptyDescription>
            </EmptyHeader>
            <EmptyContent className="items-start">
              <AddConditionMenu onAdd={(type) => setGroups([[newCondition(type)]])} />
            </EmptyContent>
          </Empty>
        ) : (
          groups.map((group, groupIndex) => (
            <div key={groupIndex} className="space-y-3">
              {groupIndex > 0 && <OrDivider />}
              <ConditionGroup
                index={groupIndex}
                group={group}
                onChange={(next) => updateGroup(groupIndex, next)}
                onRemove={() => setGroups(groups.filter((_, i) => i !== groupIndex))}
              />
            </div>
          ))
        )}

        {groups.length > 0 && (
          <div className="flex justify-center">
            <Button onClick={() => setGroups([...groups, [newCondition('country')]])}>
              {__('Add group', 'yaycheckout')}
            </Button>
          </div>
        )}
      </div>

      <SheetFooter>
        <SheetClose asChild>
          <Button variant="outline">{__('Cancel', 'yaycheckout')}</Button>
        </SheetClose>
        <Button disabled>{__('Save rules', 'yaycheckout')}</Button>
      </SheetFooter>
    </>
  );
}

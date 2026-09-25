import { useState } from 'react';
import { Plus } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { TrashButton } from '@/components/shared/TrashButton';
import { LayoutStatusBadge } from '@/pages/layouts/LayoutStatusBadge';

type ConditionType = 'country' | 'product_in_cart' | 'customer_role' | 'device' | 'payment_method' | 'cart_total';

type Condition = { type: ConditionType; operator: string; value: string };

/** Conditions inside a group are ANDed; groups are ORed. */
type Group = Condition[];

const IS_OPERATORS = [
  { value: 'is', label: __('is', 'yaycheckout') },
  { value: 'is_not', label: __('is not', 'yaycheckout') },
];

const CONDITION_TYPES: Record<ConditionType, { label: string; operators: { value: string; label: string }[] }> = {
  country: {
    label: __('Country', 'yaycheckout'),
    operators: [
      { value: 'in', label: __('is one of', 'yaycheckout') },
      { value: 'not_in', label: __('is not one of', 'yaycheckout') },
    ],
  },
  product_in_cart: { label: __('Product in cart', 'yaycheckout'), operators: IS_OPERATORS },
  customer_role: { label: __('Customer role', 'yaycheckout'), operators: IS_OPERATORS },
  device: { label: __('Device', 'yaycheckout'), operators: IS_OPERATORS },
  payment_method: { label: __('Payment method', 'yaycheckout'), operators: IS_OPERATORS },
  cart_total: {
    label: __('Cart total', 'yaycheckout'),
    operators: [
      { value: 'gt', label: __('is over', 'yaycheckout') },
      { value: 'lt', label: __('is under', 'yaycheckout') },
    ],
  },
};

const CONDITION_TYPE_KEYS = Object.keys(CONDITION_TYPES) as ConditionType[];

function newCondition(type: ConditionType): Condition {
  return { type, operator: CONDITION_TYPES[type].operators[0].value, value: '' };
}

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

function ConditionGroup({
  index,
  group,
  onChange,
  onRemove,
}: {
  index: number;
  group: Group;
  onChange: (group: Group) => void;
  onRemove: () => void;
}) {
  return (
    <div className="rounded-lg border">
      <div className="bg-muted/50 flex items-center justify-between border-b px-4 py-2">
        <span className="text-muted-foreground text-xs">
          {sprintf(
            /* translators: %d: group number */
            __('Group %d — all of these must be true', 'yaycheckout'),
            index + 1,
          )}
        </span>
        <TrashButton aria-label={__('Remove group', 'yaycheckout')} onClick={onRemove} />
      </div>

      <div className="space-y-3 p-4">
        {group.map((condition, conditionIndex) => (
          <ConditionRow
            key={conditionIndex}
            condition={condition}
            onChange={(next) => onChange(group.map((c, i) => (i === conditionIndex ? next : c)))}
            onRemove={() => onChange(group.filter((_, i) => i !== conditionIndex))}
          />
        ))}

        <AddConditionMenu onAdd={(type) => onChange([...group, newCondition(type)])} />
      </div>
    </div>
  );
}

function ConditionRow({
  condition,
  onChange,
  onRemove,
}: {
  condition: Condition;
  onChange: (condition: Condition) => void;
  onRemove: () => void;
}) {
  return (
    <div className="flex items-center gap-2">
      <Select value={condition.type} onValueChange={(type) => onChange(newCondition(type as ConditionType))}>
        <SelectTrigger className="w-40 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {CONDITION_TYPE_KEYS.map((type) => (
            <SelectItem key={type} value={type}>
              {CONDITION_TYPES[type].label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select value={condition.operator} onValueChange={(operator) => onChange({ ...condition, operator })}>
        <SelectTrigger className="w-32 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {CONDITION_TYPES[condition.type].operators.map((operator) => (
            <SelectItem key={operator.value} value={operator.value}>
              {operator.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {/* ponytail: free-text value for every type; per-type pickers (countries, roles, products) come with the content pass. */}
      <Input
        className="min-w-0 flex-1"
        type={condition.type === 'cart_total' ? 'number' : 'text'}
        value={condition.value}
        onChange={(event) => onChange({ ...condition, value: event.target.value })}
      />

      <TrashButton aria-label={__('Remove condition', 'yaycheckout')} onClick={onRemove} />
    </div>
  );
}

function OrDivider() {
  return (
    <div className="flex items-center gap-3">
      <div className="bg-border h-px flex-1" />
      <span className="rounded border px-2 py-0.5 text-xs">{__('OR', 'yaycheckout')}</span>
      <span className="text-muted-foreground text-xs">{__('any group can match', 'yaycheckout')}</span>
      <div className="bg-border h-px flex-1" />
    </div>
  );
}

function AddConditionMenu({ onAdd }: { onAdd: (type: ConditionType) => void }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline">
          <Plus />
          {__('Add condition', 'yaycheckout')}
        </Button>
      </DropdownMenuTrigger>
      {/* Above the Sheet (z-99999); SelectContent uses the same layer. */}
      <DropdownMenuContent align="start" className="z-100005 w-56">
        <DropdownMenuLabel className="text-muted-foreground text-xs tracking-wider uppercase">
          {__('Condition type', 'yaycheckout')}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {CONDITION_TYPE_KEYS.map((type) => (
          <DropdownMenuItem key={type} onSelect={() => onAdd(type)}>
            {CONDITION_TYPES[type].label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

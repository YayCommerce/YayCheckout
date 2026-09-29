import { Plus } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ConfirmPopover } from '@/components/shared/ConfirmPopover';
import { TrashButton } from '@/components/shared/TrashButton';
import {
  CONDITION_TYPE_KEYS,
  CONDITION_TYPES,
  isKnownType,
  newCondition,
  storeData,
  type Condition,
  type ConditionType,
  type Group,
} from '@/pages/layouts/assignment/condition-types';
import { OptionsMultiSelect } from '@/pages/layouts/assignment/OptionsMultiSelect';

/** Types whose value comes from a list the page already holds. Functions, so window is read at render, not import. */
const OPTION_PICKERS = {
  customer_role: () => ({
    options: storeData().roles,
    placeholder: __('Select roles', 'yaycheckout'),
    searchPlaceholder: __('Search roles', 'yaycheckout'),
    emptyText: __('No roles found.', 'yaycheckout'),
  }),
} satisfies Partial<Record<ConditionType, () => object>>;

export function ConditionGroup({
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
        {/* A group can hold several conditions, so removing it asks first; a single condition row does not. */}
        <ConfirmPopover
          trigger={<TrashButton aria-label={__('Remove group', 'yaycheckout')} />}
          message={__('Remove this group and all its conditions?', 'yaycheckout')}
          confirmLabel={__('Remove', 'yaycheckout')}
          onConfirm={onRemove}
        />
      </div>

      <div className="space-y-3 p-4">
        {group.conditions.map((condition) => (
          <ConditionRow
            key={condition.id}
            condition={condition}
            onChange={(next) =>
              onChange({
                ...group,
                conditions: group.conditions.map((other) => (other.id === condition.id ? next : other)),
              })
            }
            onRemove={() =>
              onChange({ ...group, conditions: group.conditions.filter((other) => other.id !== condition.id) })
            }
          />
        ))}

        <AddConditionMenu
          onAdd={(type) => onChange({ ...group, conditions: [...group.conditions, newCondition(type)] })}
        />
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
  if (!isKnownType(condition.type)) {
    return (
      <div className="flex items-center gap-2">
        <p className="border-warning/50 bg-warning/10 flex-1 rounded-md border px-3 py-2 text-sm">
          {sprintf(
            /* translators: %s: condition type key */
            __('"%s" is not supported by this version. Remove it to save the rules.', 'yaycheckout'),
            condition.type,
          )}
        </p>
        <TrashButton aria-label={__('Remove condition', 'yaycheckout')} onClick={onRemove} />
      </div>
    );
  }

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
        <SelectTrigger className="w-40 shrink-0">
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

      <ValuePicker condition={condition} onChange={(value) => onChange({ ...condition, value })} />

      <TrashButton aria-label={__('Remove condition', 'yaycheckout')} onClick={onRemove} />
    </div>
  );
}

/** The third column: the picker the condition's type takes its value from. */
function ValuePicker({ condition, onChange }: { condition: Condition; onChange: (value: Condition['value']) => void }) {
  if (condition.type in OPTION_PICKERS) {
    return (
      <OptionsMultiSelect
        className="flex-1"
        {...OPTION_PICKERS[condition.type as keyof typeof OPTION_PICKERS]()}
        value={condition.value as string[]}
        onChange={onChange}
      />
    );
  }

  return (
    <Select value={condition.value as string} onValueChange={onChange}>
      <SelectTrigger className="min-w-0 flex-1">
        <SelectValue placeholder={__('Select a value', 'yaycheckout')} />
      </SelectTrigger>
      <SelectContent>
        {CONDITION_TYPES[condition.type].choices?.map((choice) => (
          <SelectItem key={choice.value} value={choice.value}>
            {choice.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

export function OrDivider() {
  return (
    <div className="flex items-center gap-3">
      <div className="bg-border h-px flex-1" />
      <span className="rounded border px-2 py-0.5 text-xs">{__('OR', 'yaycheckout')}</span>
      <span className="text-muted-foreground text-xs">{__('any group can match', 'yaycheckout')}</span>
      <div className="bg-border h-px flex-1" />
    </div>
  );
}

export function AddConditionMenu({ onAdd }: { onAdd: (type: ConditionType) => void }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline">
          <Plus />
          {__('Add condition', 'yaycheckout')}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-56">
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

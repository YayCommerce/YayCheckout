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
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TrashButton } from '@/components/shared/TrashButton';
import {
  CONDITION_TYPE_KEYS,
  CONDITION_TYPES,
  DEVICE_OPTIONS,
  newCondition,
  type Condition,
  type ConditionType,
  type Group,
} from '@/pages/layouts/assignment/condition-types';
import { OptionsMultiSelect } from '@/pages/layouts/assignment/OptionsMultiSelect';
import { ProductMultiSelect } from '@/pages/layouts/assignment/ProductMultiSelect';

/** Types whose value comes from a list the page already holds. Functions, so window is read at render, not import. */
const OPTION_PICKERS = {
  country: () => ({
    options: window.yayCheckoutAdmin.countries,
    placeholder: __('Select countries', 'yaycheckout'),
    searchPlaceholder: __('Search countries', 'yaycheckout'),
    emptyText: __('No countries found.', 'yaycheckout'),
  }),
  customer_role: () => ({
    options: window.yayCheckoutAdmin.roles,
    placeholder: __('Select roles', 'yaycheckout'),
    searchPlaceholder: __('Search roles', 'yaycheckout'),
    emptyText: __('No roles found.', 'yaycheckout'),
  }),
  payment_method: () => ({
    options: window.yayCheckoutAdmin.gateways,
    placeholder: __('Select payment methods', 'yaycheckout'),
    searchPlaceholder: __('Search payment methods', 'yaycheckout'),
    emptyText: __('No payment methods found.', 'yaycheckout'),
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

      {condition.type in OPTION_PICKERS ? (
        <OptionsMultiSelect
          className="flex-1"
          {...OPTION_PICKERS[condition.type as keyof typeof OPTION_PICKERS]()}
          value={condition.value as string[]}
          onChange={(value) => onChange({ ...condition, value })}
        />
      ) : condition.type === 'product_in_cart' ? (
        <ProductMultiSelect
          className="flex-1"
          value={condition.value as string[]}
          onChange={(value) => onChange({ ...condition, value })}
        />
      ) : condition.type === 'device' ? (
        <Select value={condition.value as string} onValueChange={(value) => onChange({ ...condition, value })}>
          <SelectTrigger className="min-w-0 flex-1">
            <SelectValue placeholder={__('Select device', 'yaycheckout')} />
          </SelectTrigger>
          <SelectContent>
            {DEVICE_OPTIONS.map((device) => (
              <SelectItem key={device.value} value={device.value}>
                {device.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ) : (
        // ponytail: free text for the remaining types; their pickers (roles, products, …) come type by type.
        <Input
          className="min-w-0 flex-1"
          type={condition.type === 'cart_total' ? 'number' : 'text'}
          value={condition.value as string}
          onChange={(event) => onChange({ ...condition, value: event.target.value })}
        />
      )}

      <TrashButton aria-label={__('Remove condition', 'yaycheckout')} onClick={onRemove} />
    </div>
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

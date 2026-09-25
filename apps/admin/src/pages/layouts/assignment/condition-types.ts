import { __ } from '@wordpress/i18n';

import type { StoreData } from '@/types/localize.type';
import type { LayoutRulesWire } from '@/lib/schema/layout.schema';

/** Mirrors Enums\ConditionType (types, operators, devices, value kind); PHP validates on save. */
export type ConditionType =
  'country' | 'product_in_cart' | 'customer_role' | 'device' | 'payment_method' | 'cart_total';

/**
 * Value is a list for multi-select types (country codes, product ids, role slugs, gateway ids), an amount for cart total, a key otherwise.
 * `id` is form-only, the React key: rows keyed by position would hand a removed row's picker state to the next one.
 */
export type Condition = { id: string; type: ConditionType; operator: string; value: string | string[] | number };

/** Conditions inside a group are ANDed; groups are ORed. `id` is form-only, as on Condition. */
export type Group = { id: string; conditions: Condition[] };

let lastId = 0;

/** A form-only row id; never sent. */
export function newId(): string {
  lastId += 1;

  return String(lastId);
}

const IS_OPERATORS = [
  { value: 'is', label: __('is', 'yaycheckout') },
  { value: 'is_not', label: __('is not', 'yaycheckout') },
];

/** For multi-select types: the value is a list. */
const LIST_OPERATORS = [
  { value: 'in', label: __('in list', 'yaycheckout') },
  { value: 'not_in', label: __('not in list', 'yaycheckout') },
];

export const DEVICE_OPTIONS = [
  { value: 'all', label: __('All', 'yaycheckout') },
  { value: 'desktop', label: __('Desktop', 'yaycheckout') },
  { value: 'mobile', label: __('Mobile', 'yaycheckout') },
];

export const CONDITION_TYPES: Record<ConditionType, { label: string; operators: { value: string; label: string }[] }> =
  {
    country: { label: __('Country', 'yaycheckout'), operators: LIST_OPERATORS },
    product_in_cart: { label: __('Product in cart', 'yaycheckout'), operators: LIST_OPERATORS },
    customer_role: { label: __('Customer role', 'yaycheckout'), operators: LIST_OPERATORS },
    device: { label: __('Device', 'yaycheckout'), operators: IS_OPERATORS },
    payment_method: { label: __('Payment method', 'yaycheckout'), operators: LIST_OPERATORS },
    cart_total: {
      label: __('Cart total', 'yaycheckout'),
      operators: [
        { value: 'gt', label: __('is over', 'yaycheckout') },
        { value: 'lt', label: __('is under', 'yaycheckout') },
      ],
    },
  };

export const CONDITION_TYPE_KEYS = Object.keys(CONDITION_TYPES) as ConditionType[];

/** Types whose value is picked from a list, several at once. */
const MULTI_VALUE_TYPES: ConditionType[] = ['country', 'product_in_cart', 'customer_role', 'payment_method'];

function emptyValue(type: ConditionType): Condition['value'] {
  if (MULTI_VALUE_TYPES.includes(type)) return [];
  if (type === 'cart_total') return 0;

  return '';
}

export function newCondition(type: ConditionType): Condition {
  return { id: newId(), type, operator: CONDITION_TYPES[type].operators[0].value, value: emptyValue(type) };
}

/** Whether this version has a picker for the type. Stored rules may carry one it lacks, e.g. after a downgrade. */
export function isKnownType(type: string): type is ConditionType {
  return Object.hasOwn(CONDITION_TYPES, type);
}

/** Whether the condition has a value to test. A cart total of 0 counts: "is over 0" is a real rule. An unknown type never is. */
export function isConditionComplete(condition: Condition): boolean {
  if (!isKnownType(condition.type)) return false;
  if (Array.isArray(condition.value)) return condition.value.length > 0;
  if (typeof condition.value === 'number') return true;
  return condition.value !== '';
}

/** Stored rules to form state: list values become strings, as the pickers key their options by string. */
export function groupsFromWire(rules: LayoutRulesWire): Group[] {
  return rules.map((group) => ({
    id: newId(),
    conditions: group.map((condition) => ({
      id: newId(),
      // Unknown types are kept, not dropped: the row says so, and saving waits until the merchant removes it.
      type: condition.type as ConditionType,
      operator: condition.operator,
      value: Array.isArray(condition.value) ? condition.value.map(String) : condition.value,
    })),
  }));
}

/** Form state to the rules the route stores: ids stripped. */
export function groupsToWire(groups: Group[]): LayoutRulesWire {
  return groups.map((group) => group.conditions.map(({ type, operator, value }) => ({ type, operator, value })));
}

/** The page's store lists and price format. Only the Pro page carries them, and only Pro renders the drawer. */
export function storeData(): StoreData {
  const store = window.yayCheckoutAdmin.store;
  if (!store) throw new Error('window.yayCheckoutAdmin.store is missing: the Assignment drawer is Pro only.');

  return store;
}

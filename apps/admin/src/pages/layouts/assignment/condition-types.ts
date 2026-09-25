import { __ } from '@wordpress/i18n';

import type { LayoutRulesWire } from '@/lib/schema/layout.schema';

export type ConditionType =
  'country' | 'product_in_cart' | 'customer_role' | 'device' | 'payment_method' | 'cart_total';

/** Value is a list for multi-select types (country codes, product ids, role slugs, gateway ids), an amount for cart total, a key otherwise. */
export type Condition = { type: ConditionType; operator: string; value: string | string[] | number };

/** Conditions inside a group are ANDed; groups are ORed. */
export type Group = Condition[];

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

export function newCondition(type: ConditionType): Condition {
  return {
    type,
    operator: CONDITION_TYPES[type].operators[0].value,
    value: MULTI_VALUE_TYPES.includes(type) ? [] : type === 'cart_total' ? 0 : '',
  };
}

/** Whether the condition has a value to test. A cart total of 0 counts: "is over 0" is a real rule. */
export function isConditionComplete(condition: Condition): boolean {
  if (Array.isArray(condition.value)) return condition.value.length > 0;
  if (typeof condition.value === 'number') return true;
  return condition.value !== '';
}

/** Stored rules to form state: list values become strings, as the pickers key their options by string. */
export function groupsFromWire(rules: LayoutRulesWire): Group[] {
  return rules.map((group) =>
    group.map((condition) => ({
      type: condition.type as ConditionType,
      operator: condition.operator,
      value: Array.isArray(condition.value) ? condition.value.map(String) : condition.value,
    })),
  );
}

import { __ } from '@wordpress/i18n';

import type { StoreData } from '@/types/localize.type';
import type { LayoutRulesWire } from '@/lib/schema/layout.schema';

/** Mirrors Enums\ConditionType (types, operators, value kind, choices); PHP validates on save. */
export type ConditionType = 'customer_role' | 'login_status' | 'device';

/**
 * Value is a list for multi-select types (role slugs), one of the type's `choices` otherwise.
 * `id` is form-only, the React key: rows keyed by position would hand a removed row's picker state to the next one.
 */
export type Condition = { id: string; type: ConditionType; operator: string; value: string | string[] };

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

const LIST_OPERATORS = [
  { value: 'in', label: __('in list', 'yaycheckout') },
  { value: 'not_in', label: __('not in list', 'yaycheckout') },
];

type Option = { value: string; label: string };

/** `choices`: the values a single-choice type takes; types without it pick several from a list. */
export const CONDITION_TYPES: Record<ConditionType, { label: string; operators: Option[]; choices?: Option[] }> = {
  customer_role: { label: __('Customer role', 'yaycheckout'), operators: LIST_OPERATORS },
  login_status: {
    label: __('Login status', 'yaycheckout'),
    operators: IS_OPERATORS,
    choices: [
      { value: 'logged_in', label: __('Logged in', 'yaycheckout') },
      { value: 'guest', label: __('Guest', 'yaycheckout') },
    ],
  },
  device: {
    label: __('Device', 'yaycheckout'),
    operators: IS_OPERATORS,
    choices: [
      { value: 'desktop', label: __('Desktop', 'yaycheckout') },
      { value: 'mobile', label: __('Mobile', 'yaycheckout') },
    ],
  },
};

export const CONDITION_TYPE_KEYS = Object.keys(CONDITION_TYPES) as ConditionType[];

function emptyValue(type: ConditionType): Condition['value'] {
  return CONDITION_TYPES[type].choices ? '' : [];
}

export function newCondition(type: ConditionType): Condition {
  return { id: newId(), type, operator: CONDITION_TYPES[type].operators[0].value, value: emptyValue(type) };
}

/** Whether this version has a picker for the type. Stored rules may carry one it lacks, e.g. after a downgrade. */
export function isKnownType(type: string): type is ConditionType {
  return Object.hasOwn(CONDITION_TYPES, type);
}

/** Whether the condition has a value to test. One with an unknown type never does. */
export function isConditionComplete(condition: Condition): boolean {
  if (!isKnownType(condition.type)) return false;
  if (Array.isArray(condition.value)) return condition.value.length > 0;
  return condition.value !== '';
}

/** Stored rules to form state: values become strings, as the pickers key their options by string. */
export function groupsFromWire(rules: LayoutRulesWire): Group[] {
  return rules.map((group) => ({
    id: newId(),
    conditions: group.map((condition) => ({
      id: newId(),
      // Unknown types are kept, not dropped: the row says so, and saving waits until the merchant removes it.
      type: condition.type as ConditionType,
      operator: condition.operator,
      value: Array.isArray(condition.value) ? condition.value.map(String) : String(condition.value),
    })),
  }));
}

/** Form state to the rules the route stores: ids stripped. */
export function groupsToWire(groups: Group[]): LayoutRulesWire {
  return groups.map((group) => group.conditions.map(({ type, operator, value }) => ({ type, operator, value })));
}

/** The page's store lists. Only the Pro page carries them, and only Pro renders the drawer. */
export function storeData(): StoreData {
  const store = window.yayCheckoutAdmin.store;
  if (!store) throw new Error('window.yayCheckoutAdmin.store is missing: the Assignment drawer is Pro only.');

  return store;
}

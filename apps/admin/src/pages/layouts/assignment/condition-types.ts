import { __ } from '@wordpress/i18n';

export type ConditionType =
  'country' | 'product_in_cart' | 'customer_role' | 'device' | 'payment_method' | 'cart_total';

/** Value is a list for multi-select types (country codes), text otherwise. */
export type Condition = { type: ConditionType; operator: string; value: string | string[] };

/** Conditions inside a group are ANDed; groups are ORed. */
export type Group = Condition[];

const IS_OPERATORS = [
  { value: 'is', label: __('is', 'yaycheckout') },
  { value: 'is_not', label: __('is not', 'yaycheckout') },
];

export const CONDITION_TYPES: Record<ConditionType, { label: string; operators: { value: string; label: string }[] }> =
  {
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

export const CONDITION_TYPE_KEYS = Object.keys(CONDITION_TYPES) as ConditionType[];

export function newCondition(type: ConditionType): Condition {
  return { type, operator: CONDITION_TYPES[type].operators[0].value, value: type === 'country' ? [] : '' };
}

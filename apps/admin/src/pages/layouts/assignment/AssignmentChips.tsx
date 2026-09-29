import { Fragment } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

import type { LayoutRulesWire } from '@/lib/schema/layout.schema';
import { Badge } from '@/components/ui/badge';
import { CONDITION_TYPES, DEVICE_OPTIONS, isKnownType, storeData } from '@/pages/layouts/assignment/condition-types';

type WireCondition = LayoutRulesWire[number][number];

/** One word for the operator, as a chip reads it: "Country is DE", "Cart total over 50". */
const OPERATOR_WORDS: Record<string, string> = {
  in: __('is', 'yaycheckout'),
  is: __('is', 'yaycheckout'),
  not_in: __('is not', 'yaycheckout'),
  is_not: __('is not', 'yaycheckout'),
  gt: __('over', 'yaycheckout'),
  lt: __('under', 'yaycheckout'),
};

function valueText({ type, value }: WireCondition): string {
  if (!Array.isArray(value)) {
    return DEVICE_OPTIONS.find((device) => device.value === value)?.label ?? String(value);
  }

  if (type === 'product_in_cart') {
    return sprintf(_n('%d product', '%d products', value.length, 'yaycheckout'), value.length);
  }

  if (type === 'customer_role') {
    return value.map((key) => storeData().roles[key] ?? key).join(', ');
  }

  return value.join(', ');
}

function chipText(condition: WireCondition): string {
  // A type this version has no picker for still shows, raw: hiding it would hide why the layout is not served.
  const label = isKnownType(condition.type) ? CONDITION_TYPES[condition.type].label : condition.type;

  return `${label} ${OPERATOR_WORDS[condition.operator] ?? condition.operator} ${valueText(condition)}`;
}

/** A layout's rules as one line of chips for the Layouts table, groups separated by "or". No rules applies to everyone. */
export function AssignmentChips({ rules }: { rules: LayoutRulesWire }) {
  if (rules.length === 0) {
    return <span className="text-muted-foreground">{__('Everyone', 'yaycheckout')}</span>;
  }

  const groups = rules.map((group) => group.map(chipText));

  return (
    <div
      className="flex items-center gap-1 overflow-hidden whitespace-nowrap"
      title={groups.map((chips) => chips.join(' + ')).join(` ${__('or', 'yaycheckout')} `)}
    >
      {groups.map((chips, groupIndex) => (
        <Fragment key={groupIndex}>
          {groupIndex > 0 && <span className="text-muted-foreground text-xs">{__('or', 'yaycheckout')}</span>}
          {chips.map((text, chipIndex) => (
            <Badge key={chipIndex} variant="outline" className="shrink-0">
              {text}
            </Badge>
          ))}
        </Fragment>
      ))}
    </div>
  );
}

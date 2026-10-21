import { __ } from '@wordpress/i18n';

import type { LayoutRulesWire } from '@/lib/schema/layout.schema';
import { CONDITION_TYPES, isKnownType, storeData } from '@/pages/layouts/assignment/condition-types';
import { OverflowBadges } from '@/pages/layouts/OverflowBadges';

type WireCondition = LayoutRulesWire[number][number];

function valueText({ type, value }: WireCondition): string {
  if (!Array.isArray(value)) {
    const choices = isKnownType(type) ? CONDITION_TYPES[type].choices : undefined;
    return choices?.find((choice) => choice.value === value)?.label ?? String(value);
  }

  if (type === 'customer_role') {
    return value.map((key) => storeData().roles[key] ?? key).join(', ');
  }

  return value.join(', ');
}

function chipText(condition: WireCondition): string {
  // A type this version has no picker for still shows, raw: hiding it would hide why the layout is not served.
  const known = isKnownType(condition.type) ? CONDITION_TYPES[condition.type] : null;
  const operator = known?.operators.find((option) => option.value === condition.operator)?.label ?? condition.operator;

  return `${known?.label ?? condition.type} ${operator} ${valueText(condition)}`;
}

/** A layout's rules as chips for the Layouts table, groups separated by "or", past two folded into "+N". No rules means everyone. */
export function AssignmentChips({ rules }: { rules: LayoutRulesWire }) {
  if (rules.length === 0) {
    return <span className="text-muted-foreground">{__('Everyone', 'yaycheckout')}</span>;
  }

  return <OverflowBadges groups={rules.map((group) => group.map(chipText))} joiner={__('or', 'yaycheckout')} />;
}

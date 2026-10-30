import { __ } from '@wordpress/i18n';

import { useAssignmentOptionLabels } from '@/lib/queries/assignment.queries';
import type { AssignmentOptionKind } from '@/lib/schema/assignment.schema';
import type { LayoutRulesWire } from '@/lib/schema/layout.schema';
import { CONDITION_TYPES, isKnownType, storeData } from '@/pages/layouts/assignment/condition-types';
import { OverflowBadges } from '@/pages/layouts/OverflowBadges';

type WireCondition = LayoutRulesWire[number][number];

/** Id => label, per searched kind. */
type Labels = Record<AssignmentOptionKind, Record<string, string> | undefined>;

function valueText({ type, value }: WireCondition, labels: Labels): string {
  if (!Array.isArray(value)) {
    const choices = isKnownType(type) ? CONDITION_TYPES[type].choices : undefined;
    return choices?.find((choice) => choice.value === value)?.label ?? String(value);
  }

  if (type === 'customer_role') {
    return value.map((key) => storeData().roles[key] ?? key).join(', ');
  }

  const kind = isKnownType(type) ? CONDITION_TYPES[type].ids : undefined;
  if (kind) {
    return value.map((id) => labels[kind]?.[id] ?? `#${id}`).join(', ');
  }

  return value.join(', ');
}

function chipText(condition: WireCondition, labels: Labels): string {
  // A type this version has no picker for still shows, raw: hiding it would hide why the layout is not served.
  const known = isKnownType(condition.type) ? CONDITION_TYPES[condition.type] : null;
  const operator = known?.operators.find((option) => option.value === condition.operator)?.label ?? condition.operator;

  return `${known?.label ?? condition.type} ${operator} ${valueText(condition, labels)}`;
}

/** The ids this layout's conditions of one searched kind list. */
function idsOf(rules: LayoutRulesWire, kind: AssignmentOptionKind): number[] {
  return rules.flatMap((group) =>
    group.flatMap(({ type, value }) =>
      isKnownType(type) && CONDITION_TYPES[type].ids === kind && Array.isArray(value) ? value.map(Number) : [],
    ),
  );
}

/** A layout's rules as chips for the Layouts table, groups separated by "or", past two folded into "+N". No rules means everyone. */
export function AssignmentChips({ rules }: { rules: LayoutRulesWire }) {
  // Looked up per row: only rows with cart conditions ask, and the drawer's pickers share the cache.
  const products = useAssignmentOptionLabels('products', idsOf(rules, 'products'));
  const categories = useAssignmentOptionLabels('categories', idsOf(rules, 'categories'));
  const labels = { products: products.data, categories: categories.data };

  if (rules.length === 0) {
    return <span className="text-muted-foreground">{__('Everyone', 'yaycheckout')}</span>;
  }

  return (
    <OverflowBadges
      groups={rules.map((group) => group.map((condition) => chipText(condition, labels)))}
      joiner={__('or', 'yaycheckout')}
    />
  );
}

import { __ } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Badge } from '@/components/ui/badge';

/**
 * A layout's state as merchants see it: membership in the active set, not post_status.
 * The Layouts table, the Tools picker and the assignment drawer reuse it.
 */
export function LayoutStatusBadge({ layout, className }: { layout: LayoutSummary; className?: string }) {
  if (layout.status === 'trash') {
    return (
      <Badge variant="destructive-soft" className={className}>
        {__('Archived', 'yaycheckout')}
      </Badge>
    );
  }

  if (layout.isActive) {
    return (
      <Badge variant="success" className={className}>
        {__('Active', 'yaycheckout')}
      </Badge>
    );
  }

  return (
    <Badge variant="outline" className={className}>
      {__('Inactive', 'yaycheckout')}
    </Badge>
  );
}

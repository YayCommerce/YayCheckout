import { __, sprintf } from '@wordpress/i18n';

import { formatDateTime } from '@/lib/helpers/date.helper';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Badge } from '@/components/ui/badge';

/** A layout's post status as the Layouts table shows it; the Tools picker reuses it. */
export function LayoutStatusBadge({ layout, className }: { layout: LayoutSummary; className?: string }) {
  if (layout.status === 'trash') {
    return (
      <Badge variant="destructive-soft" className={className}>
        {__('Archived', 'yaycheckout')}
      </Badge>
    );
  }

  if (layout.status === 'publish') {
    return (
      <Badge variant="success" className={className}>
        {__('Published', 'yaycheckout')}
      </Badge>
    );
  }

  if (layout.status === 'future') {
    return (
      <Badge variant="outline" className={className}>
        {layout.scheduledAt
          ? sprintf(
              /* translators: %s: scheduled publish date */
              __('Scheduled · %s', 'yaycheckout'),
              formatDateTime(layout.scheduledAt),
            )
          : __('Scheduled', 'yaycheckout')}
      </Badge>
    );
  }

  if (layout.status === 'pending') {
    return (
      <Badge variant="outline" className={className}>
        {__('Pending review', 'yaycheckout')}
      </Badge>
    );
  }

  return (
    <Badge variant="outline" className={className}>
      {__('Draft', 'yaycheckout')}
    </Badge>
  );
}

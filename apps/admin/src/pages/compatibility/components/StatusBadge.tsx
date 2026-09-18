import type { VariantProps } from 'class-variance-authority';
import { __ } from '@wordpress/i18n';

import type { GatewayStatus } from '@/lib/schema/compatibility.schema';
import { Badge, type badgeVariants } from '@/components/ui/badge';

type BadgeVariant = VariantProps<typeof badgeVariants>['variant'];

/** One badge per catalogue status; the dashed border marks community claims as hearsay. */
const BADGES: Record<GatewayStatus, { label: string; variant: BadgeVariant; className?: string }> = {
  verified: { label: __('Verified', 'yaycheckout'), variant: 'success-outline' },
  community: { label: __('Community', 'yaycheckout'), variant: 'outline', className: 'border-dashed' },
  unverified: { label: __('Unverified', 'yaycheckout'), variant: 'dimmed' },
  'block-only': { label: __('Block only', 'yaycheckout'), variant: 'warning-soft' },
};

export function StatusBadge({ status }: { status: GatewayStatus }) {
  const { label, variant, className } = BADGES[status];
  return (
    <Badge variant={variant} className={className}>
      {label}
    </Badge>
  );
}

import type { VariantProps } from 'class-variance-authority';
import { __ } from '@wordpress/i18n';

import type { GatewayStatus } from '@/lib/schema/compatibility.schema';
import { cn } from '@/lib/utils';
import { Badge, type badgeVariants } from '@/components/ui/badge';

type BadgeVariant = VariantProps<typeof badgeVariants>['variant'];

/** One badge per catalogue status; the dashed border marks community claims as hearsay. */
const BADGES: Record<GatewayStatus, { label: string; variant: BadgeVariant; className?: string }> = {
  verified: { label: __('Verified', 'yaycheckout'), variant: 'success-outline' },
  community: { label: __('Community', 'yaycheckout'), variant: 'outline', className: 'border-dashed' },
  unverified: { label: __('Unverified', 'yaycheckout'), variant: 'dimmed' },
  'block-only': { label: __('Block only', 'yaycheckout'), variant: 'warning-soft' },
  conflict: { label: __('Conflict', 'yaycheckout'), variant: 'destructive-soft' },
};

/** @param title Gateway name; when set, renders a larger pill that leads with it, tinted by the status. */
export function StatusBadge({ status, title }: { status: GatewayStatus; title?: string }) {
  const { label, variant, className } = BADGES[status];

  if (!title) {
    return (
      <Badge variant={variant} className={className}>
        {label}
      </Badge>
    );
  }

  return (
    <Badge variant={variant} className={cn('h-auto gap-1.5 px-3 py-1 text-sm font-normal', className)}>
      <span className="text-foreground">{title}</span>
      <span className="text-xs">{label}</span>
    </Badge>
  );
}

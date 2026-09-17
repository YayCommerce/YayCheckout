import { ComponentProps, ReactNode } from 'react';
import { ChevronRight } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { YayCheckoutToolTip } from '@/components/shared/YayCheckoutToolTip';

const UPGRADE_URL = 'https://yaycommerce.com/yaycheckout-for-woocommerce/';

interface ProGateProps {
  children?: ReactNode;
  /** Small PRO badge instead of the full overlay. */
  inline?: boolean;
  className?: string;
}

/**
 * Cosmetic gate, not a security boundary: Free users can unblur it with
 * devtools. Safe only because the Free build's PHP has no matching endpoint.
 * Never wrap real data or a real endpoint in it; use ProOnly or a call-site
 * `isPro &&` branch.
 */
export function ProGate({ children, inline = false, className }: ProGateProps) {
  if (isPro) {
    return <>{children}</>;
  }

  if (inline) {
    return <ProBadge className={className} />;
  }

  return (
    <div className={cn('relative', className)}>
      <div className="pointer-events-none opacity-80 select-none">{children}</div>
      <div className="bg-background/20 absolute inset-0 backdrop-blur-[1px]" />
      <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
        <div className="pointer-events-auto">
          <UpgradePrompt />
        </div>
      </div>
    </div>
  );
}

/**
 * Prevents rendering only; children still ship in the bundle. To drop them,
 * branch at the call site (`{isPro && <X />}`) so the bundler can fold it.
 */
export function ProOnly({ children }: { children: ReactNode }) {
  return isPro ? <>{children}</> : null;
}

/** Runtime-only, as ProOnly. */
export function FreeOnly({ children }: { children: ReactNode }) {
  return isPro ? null : <>{children}</>;
}

/** Clickable chip for menu items and headings; links to the upgrade page. */
export function ProBadge({ className }: { className?: string }) {
  return (
    <YayCheckoutToolTip
      trigger={
        <Badge
          variant="warning"
          className={cn('text-3 h-4.5 cursor-pointer rounded-sm p-1.5 pr-0.75 leading-6 text-white', className)}
          onClick={() => window.open(UPGRADE_URL)}
        >
          {__('Pro', 'yaycheckout')}
          <ChevronRight className="h-0.75 w-1.25" />
        </Badge>
      }
      content={__('Unlock this feature', 'yaycheckout')}
    />
  );
}

/** Inert chip for a locked item; ProBadge is the clickable one. */
export function ProTag({ className }: { className?: string }) {
  return (
    <Badge variant="outline" className={cn('px-1.5 text-[10px]', className)}>
      {__('PRO', 'yaycheckout')}
    </Badge>
  );
}

/** No default onClick: not every Upgrade button goes to the same place. */
export function UpgradeButton(props: Omit<ComponentProps<typeof Button>, 'children'>) {
  return (
    <Button size="sm" variant="primary-outline" {...props}>
      {__('Upgrade', 'yaycheckout')}
    </Button>
  );
}

export function UpgradePrompt() {
  return (
    <Empty className="my-5">
      <EmptyHeader>
        <EmptyMedia variant="icon" className="size-15 bg-[#FFF5DB]">
          <img
            src={`${window.yayCheckoutMeta.checkoutMeta.assetsUrl}/images/logo/favicon.svg`}
            alt="YayCheckout"
            className="size-13"
          />
        </EmptyMedia>
        <EmptyTitle className="flex items-center gap-1.5 font-bold">
          {__('YayCheckout', 'yaycheckout')}
          <Badge variant="warning" className="text-white">
            {__('Pro', 'yaycheckout')}
          </Badge>
        </EmptyTitle>
        <EmptyDescription>{__('Upgrade to PRO to unlock this feature now', 'yaycheckout')}</EmptyDescription>
        <EmptyContent>
          <Button variant="warning" className="text-white" onClick={() => window.open(UPGRADE_URL)}>
            {__('Upgrade', 'yaycheckout')}
          </Button>
        </EmptyContent>
      </EmptyHeader>
    </Empty>
  );
}

import { ComponentProps, ReactNode } from 'react';
import { ChevronRight } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { YayCheckoutToolTip } from '@/components/shared/YayCheckoutToolTip';

const UPGRADE_URL = window.yayCheckoutMeta.checkoutMeta.upgradeUrl;

interface ProGateProps {
  children?: ReactNode;
  /** Small PRO badge instead of the full overlay. */
  inline?: boolean;
  className?: string;
}

/**
 * Renders children in a Pro build; in Free, renders them blurred under an upsell.
 * Cosmetic only: the children ship to Free, so never put real data or a real endpoint behind it.
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
 * Renders children only in a Pro build. They still ship in the Free bundle;
 * a call-site `{isPro && <X />}` branch lets the bundler drop them.
 */
export function ProOnly({ children }: { children: ReactNode }) {
  return isPro ? <>{children}</> : null;
}

/** Renders children only in a Free build; like ProOnly, they ship in both bundles. */
export function FreeOnly({ children }: { children: ReactNode }) {
  return isPro ? null : <>{children}</>;
}

/** The Pro pill for a locked item. It only labels; an UpgradeButton beside it, or ProLinkBadge instead, opens the upgrade page. */
export function ProBadge({ className }: { className?: string }) {
  return (
    <Badge variant="warning" className={cn('text-white', className)}>
      {__('Pro', 'yaycheckout')}
    </Badge>
  );
}

/** The clickable Pro chip, "Pro ›" with an "Unlock this feature" tooltip, which opens the upgrade page. For a locked item with no UpgradeButton beside it. */
export function ProLinkBadge({ className }: { className?: string }) {
  return (
    <YayCheckoutToolTip
      trigger={
        <Badge
          variant="warning"
          className={cn('h-4.5 cursor-pointer rounded-sm p-1.5 pr-0.75 leading-6 text-white', className)}
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

/** Plain "Upgrade" CTA. Opens the upgrade page; pass onClick only for a button that goes somewhere else. */
export function UpgradeButton({ onClick, ...props }: Omit<ComponentProps<typeof Button>, 'children'>) {
  return (
    <Button size="sm" variant="primary-outline" onClick={onClick ?? (() => window.open(UPGRADE_URL))} {...props}>
      {__('Upgrade', 'yaycheckout')}
    </Button>
  );
}

/** Upsell card with an Upgrade button; ProGate lays it over blurred children. */
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
          <ProBadge />
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

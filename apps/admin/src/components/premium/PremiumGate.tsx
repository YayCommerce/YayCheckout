import { ReactNode } from 'react';
import { ChevronRight } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { YayCheckoutToolTip } from '@/components/ui/custom/YayCheckoutToolTip';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';

const UPGRADE_URL = 'https://yaycommerce.com/yaycheckout-for-woocommerce/';

interface PremiumGateProps {
  /** Premium content, shown blurred to Free users. */
  children?: ReactNode;
  /** Renders the small inline PRO badge instead of the full overlay. */
  inline?: boolean;
  className?: string;
}

/**
 * Shows premium content to Pro builds and a blurred upsell to Free builds.
 *
 * IMPORTANT — this gate is cosmetic, not a security boundary. Free users
 * receive these components in their bundle and can unblur them with devtools.
 * That is acceptable only because the Free build's PHP has no matching
 * endpoint, so revealing the UI reveals nothing and calls nothing.
 *
 * Use `PremiumOnly` instead for anything that would render real customer data
 * or call a real endpoint. If you find yourself wrapping a working data panel
 * in PremiumGate, that is the wrong component.
 */
export function PremiumGate({ children, inline = false, className }: PremiumGateProps) {
  if (isPro) {
    return <>{children}</>;
  }

  if (inline) {
    return <PremiumBadge className={className} />;
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
 * Renders children only in a Pro build.
 *
 * This prevents rendering; it does NOT remove the children from the bundle.
 * JSX compiles children into createElement arguments, so they are constructed
 * whatever this returns — verified by building both plans and grepping the
 * output.
 *
 * When the code must actually be absent from the Free bundle, branch at the
 * call site instead, where the folded constant lets the bundler drop it:
 *
 *     {isPro && <SomePremiumPanel />}
 *
 * For 1.x that distinction is cosmetic anyway: the Free build's PHP has no
 * premium endpoints, so unreferenced premium UI can call nothing.
 */
export function PremiumOnly({ children }: { children: ReactNode }) {
  return isPro ? <>{children}</> : null;
}

/** Renders children only in a Free build. Runtime-only, as PremiumOnly above. */
export function FreeOnly({ children }: { children: ReactNode }) {
  return isPro ? null : <>{children}</>;
}

/** Small PRO chip for menu items and section headings. */
export function PremiumBadge({ className }: { className?: string }) {
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

/** Standalone upsell card, for a whole page with no content to blur. */
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

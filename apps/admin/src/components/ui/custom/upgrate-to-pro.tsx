import { ChevronRight } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { YayCheckoutToolTip } from '@/components/ui/custom/YayCheckoutToolTip';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';

const yayCheckoutProUrl = 'https://yaycommerce.com/yaycheckout-for-woocommerce/';

export function UpgradeToProBadge() {
  return (
    <YayCheckoutToolTip
      trigger={
        <Badge
          variant="warning"
          className="text-3 h-4.5 cursor-pointer rounded-sm p-1.5 pr-0.75 leading-6 text-white"
          onClick={() => {
            window.open(yayCheckoutProUrl);
          }}
        >
          {__('Pro', 'yaycheckout')}
          <ChevronRight className="h-0.75 w-1.25" />
        </Badge>
      }
      content={__('Unlock this feature')}
    />
  );
}

export function UpgradeToProOverlay() {
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
          <Button
            variant="warning"
            className="text-white"
            onClick={() => {
              window.open(yayCheckoutProUrl);
            }}
          >
            {__('Upgrade', 'yaycheckout')}
          </Button>
        </EmptyContent>
      </EmptyHeader>
    </Empty>
  );
}

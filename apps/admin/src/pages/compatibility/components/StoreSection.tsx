import { __, sprintf } from '@wordpress/i18n';

import { storeTypeLabel } from '@/lib/helpers/compatibility.helper';
import { isPro } from '@/lib/plan';
import type { StoreEnvironment } from '@/lib/schema/compatibility.schema';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function StoreSection({ store }: { store: StoreEnvironment }) {
  const themeLabel = `${store.theme.name} ${store.theme.version}`.trim();
  const theme = store.theme.isBlockTheme
    ? sprintf(
        /* translators: %s: theme name and version */
        __('%s (block theme)', 'yaycheckout'),
        themeLabel,
      )
    : themeLabel;

  const rows: [string, string][] = [
    [__('Store type', 'yaycheckout'), storeTypeLabel(store.storeType)],
    [__('WooCommerce', 'yaycheckout'), store.woocommerceVersion],
    [__('WordPress', 'yaycheckout'), store.wordpressVersion],
    [__('Theme', 'yaycheckout'), theme],
    [__('Rendering', 'yaycheckout'), renderingLabel(store)],
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Store', 'yaycheckout')}</CardTitle>
      </CardHeader>
      <CardContent>
        <dl className="grid grid-cols-[max-content_1fr] gap-x-8 gap-y-2 text-sm">
          {rows.map(([label, value]) => (
            <div key={label} className="contents">
              <dt className="text-muted-foreground">{label}</dt>
              <dd>{value}</dd>
            </div>
          ))}
        </dl>
      </CardContent>
    </Card>
  );
}

/** Which checkout shoppers get, in the order CheckoutPage::resolve() decides it. */
function renderingLabel(store: StoreEnvironment) {
  if (store.safeMode) return __('Native checkout — safe mode is on', 'yaycheckout');
  if (store.activeLayouts === 0) return __('Theme checkout — no active layout', 'yaycheckout');
  // Pro picks a layout by assignment rules; a shopper no rule matches gets the theme's checkout.
  return isPro
    ? __('YayCheckout layout (shoppers no rule matches see the theme checkout)', 'yaycheckout')
    : __('YayCheckout layout', 'yaycheckout');
}

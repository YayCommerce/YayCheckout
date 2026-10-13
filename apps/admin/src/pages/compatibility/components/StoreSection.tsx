import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { __, sprintf } from '@wordpress/i18n';

import { storeTypeLabel } from '@/lib/helpers/compatibility.helper';
import { editLayoutPath } from '@/lib/helpers/layout.helper';
import type { StoreEnvironment } from '@/lib/schema/compatibility.schema';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/** Which checkout shoppers get; with problems, one red line each, with a link to the layout's editor when it is the layout's own. */
function Rendering({ safeMode, checkoutProblems }: StoreEnvironment) {
  if (checkoutProblems.length === 0) {
    return __('YayCheckout layout', 'yaycheckout');
  }

  return (
    <div className="text-destructive space-y-1">
      {checkoutProblems.map(({ reason, detail, layoutId, layoutTitle }) => (
        <p key={`${layoutId ?? 'store'}-${reason}`} title={detail ?? undefined}>
          {sprintf(
            /* translators: %s: why the layout is not served, or may break checkout */
            safeMode ? __('Native checkout — %s', 'yaycheckout') : __('YayCheckout layout — %s', 'yaycheckout'),
            reason,
          )}
          {layoutId !== null && (
            <>
              {' '}
              <Link to={editLayoutPath(layoutId)} className="font-medium underline underline-offset-2">
                {layoutTitle
                  ? sprintf(
                      /* translators: %s: layout name */
                      __('Edit %s', 'yaycheckout'),
                      layoutTitle,
                    )
                  : __('Edit layout', 'yaycheckout')}
              </Link>
            </>
          )}
        </p>
      ))}
    </div>
  );
}

export function StoreSection({ store }: { store: StoreEnvironment }) {
  const themeLabel = `${store.theme.name} ${store.theme.version}`.trim();
  const theme = store.theme.isBlockTheme
    ? sprintf(
        /* translators: %s: theme name and version */
        __('%s (block theme)', 'yaycheckout'),
        themeLabel,
      )
    : themeLabel;

  const rows: [string, ReactNode][] = [
    [__('Store type', 'yaycheckout'), storeTypeLabel(store.storeType)],
    [__('WooCommerce', 'yaycheckout'), store.woocommerceVersion],
    [__('WordPress', 'yaycheckout'), store.wordpressVersion],
    [__('Theme', 'yaycheckout'), theme],
    [__('Rendering', 'yaycheckout'), <Rendering key="rendering" {...store} />],
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

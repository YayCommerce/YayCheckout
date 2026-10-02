import { useEffect, useState, type ReactNode } from 'react';
import { Check, Circle, LoaderCircle, RefreshCw, TriangleAlert } from 'lucide-react';
import { createInterpolateElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { useCompatibilityQuery, useRescanMutation } from '@/lib/queries/compatibility.queries';
import type { CompatibilityReport } from '@/lib/schema/compatibility.schema';
import { cn } from '@/lib/utils';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/shared/StatusBadge';

const CHECKS = [
  __('Store type', 'yaycheckout'),
  __('WooCommerce version', 'yaycheckout'),
  __('Active theme', 'yaycheckout'),
  __('Payment gateways', 'yaycheckout'),
];

/** Fixed height shared by the scanning, error and report views so the popup never jumps as they swap; a long report scrolls above the pinned footer. */
const BODY = 'flex h-120 flex-col gap-6';

/** Pacing between ticks, so a fast scan still reads as one check after another. */
const TICK_MS = 400;

/**
 * Wizard step 1: one GET /compatibility, then ticks the checks off in turn.
 * Ticks start only once the report is in — a slow catalogue fetch shows as the
 * first check still spinning, not as rows claiming a result they don't have.
 */
export function ScanStep({ onContinue }: { onContinue: () => void }) {
  const query = useCompatibilityQuery();
  const rescan = useRescanMutation();
  const [ticked, setTicked] = useState(0);

  const report = rescan.isPending ? undefined : query.data;

  useEffect(() => {
    if (!report || ticked >= CHECKS.length) return;
    const timer = setTimeout(() => setTicked((count) => count + 1), TICK_MS);
    return () => clearTimeout(timer);
  }, [report, ticked]);

  function onRerun() {
    setTicked(0);
    rescan.mutate();
  }

  const error = query.error ?? rescan.error;
  if (error) {
    return (
      <div className={BODY}>
        <p className="text-destructive text-sm">{error.message}</p>
        <div className="mt-auto flex justify-between gap-2 border-t pt-4">
          <Button variant="outline" onClick={onRerun}>
            <RefreshCw />
            {__('Re-scan', 'yaycheckout')}
          </Button>
          <Button disabled>{__('Continue', 'yaycheckout')}</Button>
        </div>
      </div>
    );
  }

  if (!report || ticked < CHECKS.length) {
    return (
      <div className={BODY}>
        <header className="flex items-center gap-4">
          <LoaderCircle className="text-primary size-8 shrink-0 animate-spin" />
          <div className="space-y-1">
            <h2 className="text-lg font-semibold">{__('Scanning your store', 'yaycheckout')}</h2>
            <p className="text-muted-foreground text-sm">
              {__('Reading your setup. Nothing is changed.', 'yaycheckout')}
            </p>
          </div>
        </header>
        <ul className="divide-y rounded-sm border text-sm">
          {CHECKS.map((label, index) => (
            <CheckRow
              key={label}
              label={label}
              state={index < ticked ? 'done' : index === ticked ? 'checking' : 'pending'}
            />
          ))}
        </ul>
        <div className="flex items-center gap-4">
          <div
            className="bg-muted h-1.5 flex-1 overflow-hidden rounded-full"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={CHECKS.length}
            aria-valuenow={ticked}
          >
            <div
              className="bg-primary h-full transition-[width] duration-300"
              style={{ width: `${(ticked / CHECKS.length) * 100}%` }}
            />
          </div>
          <span className="text-muted-foreground text-sm">
            {sprintf(
              /* translators: 1: finished checks, 2: total checks */
              __('%1$d of %2$d', 'yaycheckout'),
              ticked,
              CHECKS.length,
            )}
          </span>
        </div>
        <div className="mt-auto flex justify-end border-t pt-4">
          <Button disabled>{__('Continue', 'yaycheckout')}</Button>
        </div>
      </div>
    );
  }

  return <ScanReport report={report} onRerun={onRerun} onContinue={onContinue} />;
}

function CheckRow({ label, state }: { label: string; state: 'done' | 'checking' | 'pending' }) {
  return (
    <li
      className={cn(
        'flex items-center gap-4 px-4 py-2.5',
        state === 'checking' ? 'bg-primary/5' : 'bg-muted/40',
        state === 'pending' && 'text-muted-foreground',
      )}
    >
      {state === 'done' && <Check className="text-success size-4" />}
      {state === 'checking' && <LoaderCircle className="text-primary size-4 animate-spin" />}
      {state === 'pending' && <Circle className="text-muted-foreground size-4 p-0.5" />}
      <span>{label}</span>
      <span
        className={cn(
          'ml-auto text-xs',
          state === 'done' && 'text-success',
          state === 'checking' && 'text-primary',
          state === 'pending' && 'text-muted-foreground',
        )}
      >
        {state === 'done' && __('done', 'yaycheckout')}
        {state === 'checking' && __('checking…', 'yaycheckout')}
        {state === 'pending' && __('pending', 'yaycheckout')}
      </span>
    </li>
  );
}

function ScanReport({
  report,
  onRerun,
  onContinue,
}: {
  report: CompatibilityReport;
  onRerun: () => void;
  onContinue: () => void;
}) {
  const { store, gateways } = report;
  const blockOnly = gateways.filter((gateway) => gateway.status === 'block-only');

  const rows: [string, ReactNode][] = [
    [
      __('Store type', 'yaycheckout'),
      store.storeType === 'block' ? (
        <>
          {__('Block checkout', 'yaycheckout')}{' '}
          <span className="text-muted-foreground">
            {__('(the Checkout block is your checkout page)', 'yaycheckout')}
          </span>
        </>
      ) : (
        <>
          {__('Classic store', 'yaycheckout')}{' '}
          <span className="text-muted-foreground">
            {__('(checkout shortcode, not the Checkout block)', 'yaycheckout')}
          </span>
        </>
      ),
    ],
    [
      __('WooCommerce', 'yaycheckout'),
      // The plugin header's "WC requires at least" keeps older versions from loading, so a scan never sees one.
      <span className="flex items-center gap-2">
        {store.woocommerceVersion}
        <Badge variant="success-outline">{__('Supported', 'yaycheckout')}</Badge>
      </span>,
    ],
    [__('Theme', 'yaycheckout'), store.theme.name],
    [
      __('Payment gateways', 'yaycheckout'),
      gateways.length === 0 ? (
        __('none enabled', 'yaycheckout')
      ) : (
        <ul className="flex flex-wrap gap-2">
          {gateways.map((gateway) => (
            <li key={gateway.id}>
              <StatusBadge status={gateway.status} title={gateway.title} />
            </li>
          ))}
        </ul>
      ),
    ],
  ];

  return (
    <div className={BODY}>
      <div className="min-h-0 flex-1 space-y-6 overflow-y-auto">
        {blockOnly.length > 0 && (
          <Alert variant="muted">
            <TriangleAlert />
            <AlertTitle>
              {sprintf(
                /* translators: %s: comma-separated gateway titles */
                _n(
                  'A block-only integration was detected: %s',
                  'Block-only integrations were detected: %s',
                  blockOnly.length,
                  'yaycheckout',
                ),
                blockOnly.map((gateway) => gateway.title).join(', '),
              )}
            </AlertTitle>
          </Alert>
        )}

        <header className="space-y-1">
          <h2 className="text-lg font-semibold">
            {blockOnly.length === 0
              ? __('Scan complete — nothing blocking', 'yaycheckout')
              : sprintf(
                  /* translators: %d: number of warnings */
                  _n(
                    'Scan complete — %d thing to know',
                    'Scan complete — %d things to know',
                    blockOnly.length,
                    'yaycheckout',
                  ),
                  blockOnly.length,
                )}
          </h2>
          <p className="text-muted-foreground text-sm">
            {blockOnly.length === 0
              ? sprintf(
                  /* translators: %d: number of checks */
                  __('%d checks passed. YayCheckout can render a layout on your store.', 'yaycheckout'),
                  CHECKS.length,
                )
              : _n(
                  'Nothing blocks setup. One gateway will not appear in your layout.',
                  'Nothing blocks setup. Some gateways will not appear in your layout.',
                  blockOnly.length,
                  'yaycheckout',
                )}
          </p>
        </header>

        <dl className="divide-y rounded-sm border text-sm">
          {rows.map(([label, value]) => (
            <div key={label} className="grid grid-cols-[minmax(0,2fr)_minmax(0,5fr)]">
              <dt className="bg-muted/40 text-muted-foreground border-r px-4 py-3 text-sm/6">{label}</dt>
              <dd className="px-4 py-3 leading-6">{value}</dd>
            </div>
          ))}
        </dl>

        {blockOnly.length > 0 && (
          <p className="text-muted-foreground text-sm">
            {__(
              'Block-only gateways work only with the WooCommerce Checkout block and will not appear in your YayCheckout layout.',
              'yaycheckout',
            )}
          </p>
        )}

        <p className="text-muted-foreground text-sm">
          {createInterpolateElement(__('You can re-run this any time from <a>Compatibility</a>.', 'yaycheckout'), {
            // New tab: the wizard can't be closed, so an in-app route change would land behind it.
            a: <a href="#/compatibility" target="_blank" rel="noreferrer" className="text-primary underline" />,
          })}
        </p>
      </div>

      <div className="flex justify-end gap-2 border-t pt-4">
        <Button variant="outline" onClick={onRerun}>
          <RefreshCw />
          {__('Re-scan', 'yaycheckout')}
        </Button>
        <Button onClick={onContinue}>{__('Continue', 'yaycheckout')}</Button>
      </div>
    </div>
  );
}

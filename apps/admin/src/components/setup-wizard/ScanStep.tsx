import { useEffect, useState, type ReactNode } from 'react';
import { LoaderCircle, RefreshCw } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import { deriveWarnings, storeTypeLabel } from '@/lib/helpers/compatibility.helper';
import { useCompatibilityQuery, useRescanMutation } from '@/lib/queries/compatibility.queries';
import type { CompatibilityReport } from '@/lib/schema/compatibility.schema';
import { Badge } from '@/components/ui/badge';
import { Button, LoadingButton } from '@/components/ui/button';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { WarningBanner } from '@/components/shared/WarningBanner';

const CHECKS: { label: string; renderValue: (report: CompatibilityReport) => ReactNode }[] = [
  {
    label: __('Store type', 'yaycheckout'),
    renderValue: ({ store }) => (
      <>
        {storeTypeLabel(store.storeType)}{' '}
        <span className="text-muted-foreground">
          {store.storeType === 'block'
            ? __('(the Checkout block is your checkout page)', 'yaycheckout')
            : __('(checkout shortcode, not the Checkout block)', 'yaycheckout')}
        </span>
      </>
    ),
  },
  {
    label: __('WooCommerce', 'yaycheckout'),
    // The plugin header's "WC requires at least" keeps older versions from loading, so a scan never sees one.
    renderValue: ({ store }) => (
      <span className="flex items-center gap-2">
        {store.woocommerceVersion}
        <Badge variant="success-outline">{__('Supported', 'yaycheckout')}</Badge>
      </span>
    ),
  },
  {
    label: __('Theme', 'yaycheckout'),
    renderValue: ({ store }) => store.theme.name,
  },
  {
    label: __('Payment gateways', 'yaycheckout'),
    renderValue: ({ gateways }) =>
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
  },
];

/** Fixed height shared by the error and results views so the popup never jumps as they swap; a long report scrolls above the pinned footer. */
const BODY_CLASS = 'flex h-120 flex-col gap-6';

type CheckState = 'done' | 'checking' | 'pending';

/** Pacing between ticks, so a fast scan still reads as one check after another. */
const TICK_MS = 400;

/**
 * Wizard step 1: one GET /compatibility, shown as the results table from the
 * start. Each row's value fills in as its tick lands; ticks start only once the
 * report is in, so a slow request shows as the first row still spinning, not as
 * rows claiming a result they don't have.
 */
export function ScanStep({ onContinue }: { onContinue: () => void }) {
  const query = useCompatibilityQuery();
  const rescan = useRescanMutation();
  // A report already cached (Back from a later step) was ticked through once; show it finished.
  const [ticked, setTicked] = useState(() => (query.data ? CHECKS.length : 0));

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

  // The request error itself (status, REST URL) is for the console, not the merchant.
  if (query.error || rescan.error) {
    return <ScanError isRescanning={rescan.isPending} onRerun={onRerun} />;
  }

  return <ScanResults report={report} ticked={ticked} onRerun={onRerun} onContinue={onContinue} />;
}

function ScanError({ isRescanning, onRerun }: { isRescanning: boolean; onRerun: () => void }) {
  return (
    <div className={BODY_CLASS}>
      <p className="text-destructive text-sm">
        {__("We couldn't scan your store. Check your connection and try again.", 'yaycheckout')}
      </p>
      <div className="mt-auto flex justify-between gap-2 border-t pt-4">
        <LoadingButton variant="outline" loading={isRescanning} disabled={isRescanning} onClick={onRerun}>
          <RefreshCw />
          {__('Re-scan', 'yaycheckout')}
        </LoadingButton>
        <Button disabled>{__('Continue', 'yaycheckout')}</Button>
      </div>
    </div>
  );
}

function checkState(index: number, ticked: number): CheckState {
  if (index < ticked) return 'done';
  return index === ticked ? 'checking' : 'pending';
}

/** @param report undefined while the request is in flight. */
function ScanResults({
  report,
  ticked,
  onRerun,
  onContinue,
}: {
  report: CompatibilityReport | undefined;
  ticked: number;
  onRerun: () => void;
  onContinue: () => void;
}) {
  const isDone = !!report && ticked >= CHECKS.length;

  return (
    <div className={BODY_CLASS}>
      <div className="min-h-0 flex-1 space-y-6 overflow-y-auto">
        {isDone ? (
          <ScanSummary report={report} />
        ) : (
          <header className="space-y-1">
            <h2 className="text-lg font-semibold">{__('Scanning your store', 'yaycheckout')}</h2>
            <p className="text-muted-foreground text-sm">
              {__('Reading your setup. Nothing is changed.', 'yaycheckout')}
            </p>
          </header>
        )}

        <dl className="divide-y rounded-sm border text-sm">
          {CHECKS.map((check, index) => {
            const state = checkState(index, ticked);

            return (
              <div key={check.label} className="grid grid-cols-[minmax(0,2fr)_minmax(0,5fr)]">
                <dt className="bg-muted/40 text-muted-foreground border-r px-4 py-3 text-sm/6">{check.label}</dt>
                <dd className="min-h-12 px-4 py-3 leading-6">
                  {state === 'done' && report && check.renderValue(report)}
                  {state === 'checking' && (
                    <span className="flex h-6 items-center">
                      <LoaderCircle
                        className="text-primary size-4 animate-spin"
                        aria-label={__('Checking…', 'yaycheckout')}
                      />
                    </span>
                  )}
                </dd>
              </div>
            );
          })}
        </dl>

        {/* Below the table, so the rows never move when the scan finishes. */}
        {isDone && <ScanNotes report={report} />}
      </div>

      <div className="flex justify-end gap-2 border-t pt-4">
        <Button variant="outline" disabled={!isDone} onClick={onRerun}>
          <RefreshCw />
          {__('Re-scan', 'yaycheckout')}
        </Button>
        <Button disabled={!isDone} onClick={onContinue}>
          {__('Continue', 'yaycheckout')}
        </Button>
      </div>
    </div>
  );
}

/** Gateways only: plugin warnings stay on the Compatibility page. */
function wizardWarnings(report: CompatibilityReport) {
  return { ...deriveWarnings(report), unverifiedPlugins: [] };
}

function ScanSummary({ report }: { report: CompatibilityReport }) {
  const { blockOnly } = wizardWarnings(report);

  return (
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
  );
}

/** The finished scan's warning banner and block-only note. */
function ScanNotes({ report }: { report: CompatibilityReport }) {
  const warnings = wizardWarnings(report);

  return (
    <>
      <WarningBanner warnings={warnings} gatewayTotal={report.gateways.length} />

      {warnings.blockOnly.length > 0 && (
        <p className="text-muted-foreground text-sm">
          {__(
            'Block-only gateways work only with the WooCommerce Checkout block and will not appear in your YayCheckout layout.',
            'yaycheckout',
          )}
        </p>
      )}
    </>
  );
}

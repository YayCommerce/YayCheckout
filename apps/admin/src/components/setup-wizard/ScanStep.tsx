import { useEffect, useState, type ReactNode } from 'react';
import { LoaderCircle } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import { deriveWarnings, FAILED_REPORT, hasUndetected, storeTypeLabel } from '@/lib/helpers/compatibility.helper';
import { useCompatibilityQuery } from '@/lib/queries/compatibility.queries';
import type { CompatibilityReport } from '@/lib/schema/compatibility.schema';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NotDetected } from '@/components/shared/NotDetected';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { WarningBanner } from '@/components/shared/WarningBanner';

const CHECKS: { label: string; renderValue: (report: CompatibilityReport) => ReactNode }[] = [
  {
    label: __('Store type', 'yaycheckout'),
    renderValue: ({ store }) =>
      store ? (
        <>
          {storeTypeLabel(store.storeType)}{' '}
          <span className="text-muted-foreground">
            {store.storeType === 'block'
              ? __('(the Checkout block is your checkout page)', 'yaycheckout')
              : __('(checkout shortcode, not the Checkout block)', 'yaycheckout')}
          </span>
        </>
      ) : (
        <NotDetected />
      ),
  },
  {
    label: __('WooCommerce', 'yaycheckout'),
    // The plugin header's "WC requires at least" keeps older versions from loading, so a scan never sees one.
    renderValue: ({ store }) =>
      store ? (
        <span className="flex items-center gap-2">
          {store.woocommerceVersion}
          <Badge variant="success-outline">{__('Supported', 'yaycheckout')}</Badge>
        </span>
      ) : (
        <NotDetected />
      ),
  },
  {
    label: __('Theme', 'yaycheckout'),
    renderValue: ({ store }) => (store ? store.theme.name : <NotDetected />),
  },
  {
    label: __('Payment gateways', 'yaycheckout'),
    renderValue: ({ gateways }) =>
      gateways === null ? (
        <NotDetected />
      ) : gateways.length === 0 ? (
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

/** Fixed height, so the popup never jumps as the rows fill in. */
const BODY_CLASS = 'flex h-120 flex-col gap-6';

type CheckState = 'done' | 'checking' | 'pending';

/** Pacing between ticks, so a fast scan still reads as one check after another. */
const TICK_MS = 400;

/**
 * Wizard step 1: the compatibility report as a table whose rows fill in one tick at a time.
 * Ticks start only once the report is in, so a slow request shows the first row still spinning.
 * A failed request ticks through too, every row "Not detected", so the scan never blocks setup.
 */
export function ScanStep({ onContinue }: { onContinue: () => void }) {
  const query = useCompatibilityQuery();
  // A report already cached (Back from a later step) was ticked through once; show it finished.
  const [ticked, setTicked] = useState(() => (query.data ? CHECKS.length : 0));

  const report = query.data ?? (query.error && !query.isFetching ? FAILED_REPORT : undefined);

  useEffect(() => {
    if (!report || ticked >= CHECKS.length) return;
    const timer = setTimeout(() => setTicked((count) => count + 1), TICK_MS);
    return () => clearTimeout(timer);
  }, [report, ticked]);

  return <ScanResults report={report} ticked={ticked} onContinue={onContinue} />;
}

function checkState(index: number, ticked: number): CheckState {
  if (index < ticked) return 'done';
  return index === ticked ? 'checking' : 'pending';
}

/** @param report undefined while the request is in flight. */
function ScanResults({
  report,
  ticked,
  onContinue,
}: {
  report: CompatibilityReport | undefined;
  ticked: number;
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

  if (hasUndetected(report)) {
    return (
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">{__('Scan complete — some checks could not be read', 'yaycheckout')}</h2>
        <p className="text-muted-foreground text-sm">
          {__(
            'Nothing blocks setup. You can continue, and scan again later from the Compatibility page.',
            'yaycheckout',
          )}
        </p>
      </header>
    );
  }

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
      <WarningBanner warnings={warnings} gatewayTotal={report.gateways?.length ?? 0} />

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

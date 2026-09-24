import { Fragment } from 'react';
import { __ } from '@wordpress/i18n';

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

export type ImportStep = 'source' | 'draft';

export const IMPORT_STEPS: ImportStep[] = ['source', 'draft'];

const STEP_LABELS: Record<ImportStep, string> = {
  source: __('Source', 'yaycheckout'),
  draft: __('Draft', 'yaycheckout'),
};

export function StepStrip({ current }: { current: ImportStep }) {
  return (
    <Breadcrumb>
      <BreadcrumbList>
        {IMPORT_STEPS.map((step, index) => (
          <Fragment key={step}>
            {index > 0 && <BreadcrumbSeparator />}
            <BreadcrumbItem>
              {step === current ? (
                <BreadcrumbPage>{STEP_LABELS[step]}</BreadcrumbPage>
              ) : (
                <span>{STEP_LABELS[step]}</span>
              )}
            </BreadcrumbItem>
          </Fragment>
        ))}
      </BreadcrumbList>
    </Breadcrumb>
  );
}

/** Label/value strip used by the Source and Draft summaries. */
export function SummaryStrip({ items }: { items: [string, string][] }) {
  return (
    <dl className="bg-muted/30 flex flex-wrap gap-x-8 gap-y-2 rounded-md border px-4 py-2.5 text-sm">
      {items.map(([label, value]) => (
        <div key={label}>
          <dt className="text-muted-foreground mb-1 text-xs">{label}</dt>
          <dd className="font-medium">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

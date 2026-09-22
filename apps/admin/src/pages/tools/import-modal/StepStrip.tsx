import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';

export type ImportStep = 'source' | 'mapping' | 'draft';

export const IMPORT_STEPS: ImportStep[] = ['source', 'mapping', 'draft'];

const STEP_LABELS: Record<ImportStep, string> = {
  source: __('Source', 'yaycheckout'),
  mapping: __('Mapping', 'yaycheckout'),
  draft: __('Draft', 'yaycheckout'),
};

export function StepStrip({ current }: { current: ImportStep }) {
  return (
    <ol className="text-muted-foreground flex items-center gap-2 text-sm">
      {IMPORT_STEPS.map((step, index) => (
        <li key={step} className="flex items-center gap-2">
          {index > 0 && <span aria-hidden>·</span>}
          <span className={cn(step === current && 'text-foreground font-medium')}>
            {index + 1} {STEP_LABELS[step]}
          </span>
        </li>
      ))}
    </ol>
  );
}

/** Label/value strip used by the Source and Draft summaries. */
export function SummaryStrip({ items }: { items: [string, string][] }) {
  return (
    <dl className="bg-muted/30 flex flex-wrap gap-x-8 gap-y-2 rounded-md border px-4 py-2.5 text-sm">
      {items.map(([label, value]) => (
        <div key={label}>
          <dt className="text-muted-foreground text-xs">{label}</dt>
          <dd className="font-medium">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

import { __ } from '@wordpress/i18n';

import { StepBreadcrumb } from '@/components/shared/StepBreadcrumb';

export type ImportStep = 'source' | 'draft';

export const IMPORT_STEPS: ImportStep[] = ['source', 'draft'];

const STEP_LABELS: Record<ImportStep, string> = {
  source: __('Source', 'yaycheckout'),
  draft: __('Review', 'yaycheckout'),
};

export function StepStrip({ current }: { current: ImportStep }) {
  return (
    <StepBreadcrumb labels={IMPORT_STEPS.map((step) => STEP_LABELS[step])} current={IMPORT_STEPS.indexOf(current)} />
  );
}

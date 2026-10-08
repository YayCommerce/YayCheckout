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

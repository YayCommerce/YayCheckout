import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DraftStep } from './DraftStep';
import { MappingStep } from './MappingStep';
import { SourceStep } from './SourceStep';
import { IMPORT_STEPS, StepStrip, type ImportStep } from './StepStrip';

/**
 * The three-step import flow: Source → Mapping → Draft.
 *
 * Step state lives in ImportFlow inside DialogContent: Radix unmounts it after
 * the close animation, so each open starts at Source without a reset effect.
 */
export function ImportModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] sm:max-w-3xl">
        <ImportFlow onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  );
}

function ImportFlow({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState<ImportStep>('source');

  return (
    <>
      <DialogHeader className="flex-row items-center justify-between pr-8">
        <DialogTitle>{__('Import from FunnelKit', 'yaycheckout')}</DialogTitle>
        <span className="text-muted-foreground text-xs">
          {sprintf(
            /* translators: 1: current step, 2: total steps */
            __('Step %1$d of %2$d', 'yaycheckout'),
            IMPORT_STEPS.indexOf(step) + 1,
            IMPORT_STEPS.length,
          )}
        </span>
      </DialogHeader>

      <StepStrip current={step} />

      {step === 'source' && <SourceStep onCancel={onClose} onNext={() => setStep('mapping')} />}
      {step === 'mapping' && <MappingStep onBack={() => setStep('source')} onNext={() => setStep('draft')} />}
      {step === 'draft' && <DraftStep onClose={onClose} />}
    </>
  );
}

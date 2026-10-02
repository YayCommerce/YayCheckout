import { useState } from 'react';
import { useIsMutating } from '@tanstack/react-query';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { reviewExportFile } from '@/lib/api/tools.api';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { IMPORT_LAYOUT_MUTATION_KEY, useImportLayoutMutation } from '@/lib/queries/tools.queries';
import type { ImportResult, LayoutExportEnvelope } from '@/lib/schema/tools.schema';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DraftStep } from './DraftStep';
import { SourceStep } from './SourceStep';
import { IMPORT_STEPS, StepStrip, type ImportStep } from './StepStrip';

/**
 * The two-step import flow: Source → Draft.
 *
 * Step state lives in ImportFlow inside DialogContent: Radix unmounts it after
 * the close animation, so each open starts at Source without a reset effect.
 */
export function ImportModal({
  envelope,
  onOpenChange,
}: {
  envelope: LayoutExportEnvelope | null;
  onOpenChange: (open: boolean) => void;
}) {
  // The mutation lives in ImportFlow; the Dialog reads it by key so Escape cannot close mid-import.
  const importing = useIsMutating({ mutationKey: IMPORT_LAYOUT_MUTATION_KEY }) > 0;

  return (
    <Dialog open={envelope !== null} onOpenChange={(open) => !importing && onOpenChange(open)}>
      <DialogContent className="max-h-[90vh] sm:max-w-3xl">
        {envelope && <ImportFlow envelope={envelope} onClose={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  );
}

function ImportFlow({ envelope, onClose }: { envelope: LayoutExportEnvelope; onClose: () => void }) {
  const [step, setStep] = useState<ImportStep>('source');
  const [created, setCreated] = useState<ImportResult | null>(null);
  const [error, setError] = useState('');
  const importMutation = useImportLayoutMutation();

  // The flow stays on Source when PHP refuses the file, so the reason sits next to the button that asked for it.
  const createDraft = () => {
    setError('');
    importMutation.mutate(envelope, {
      onError: async (reason) => setError(await getErrorMsg(reason)),
      onSuccess: (result) => {
        setCreated(result);
        setStep('draft');
        toast.success(
          sprintf(
            /* translators: %s: layout title */
            __('Draft "%s" created.', 'yaycheckout'),
            result.layout.title,
          ),
        );
      },
    });
  };

  return (
    <>
      <DialogHeader className="flex-row items-center justify-between pr-8">
        <DialogTitle>{__('Import layout', 'yaycheckout')}</DialogTitle>
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

      {step === 'source' && (
        <SourceStep
          review={reviewExportFile(envelope)}
          isPending={importMutation.isPending}
          error={error}
          onCancel={onClose}
          onImport={createDraft}
        />
      )}
      {step === 'draft' && created && (
        <DraftStep layout={created.layout} rulesImport={created.rulesImport} onClose={onClose} />
      )}
    </>
  );
}

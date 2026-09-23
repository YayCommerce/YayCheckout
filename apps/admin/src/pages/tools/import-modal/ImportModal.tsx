import { useState } from 'react';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { useImportLayoutMutation } from '@/lib/queries/tools.queries';
import type { Layout } from '@/lib/schema/layout.schema';
import type { ImportReview, LayoutExportEnvelope } from '@/lib/schema/tools.schema';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DraftStep } from './DraftStep';
import { SourceStep } from './SourceStep';
import { IMPORT_STEPS, StepStrip, type ImportStep } from './StepStrip';

export interface ImportSession {
  envelope: LayoutExportEnvelope;
  review: ImportReview;
}

/**
 * The two-step import flow: Source → Draft.
 *
 * Step state lives in ImportFlow inside DialogContent: Radix unmounts it after
 * the close animation, so each open starts at Source without a reset effect.
 */
export function ImportModal({
  session,
  onOpenChange,
}: {
  session: ImportSession | null;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <Dialog open={session !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] sm:max-w-3xl">
        {session && <ImportFlow session={session} onClose={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  );
}

function ImportFlow({ session, onClose }: { session: ImportSession; onClose: () => void }) {
  const [step, setStep] = useState<ImportStep>('source');
  const [created, setCreated] = useState<Layout | null>(null);
  const importMutation = useImportLayoutMutation();

  // The flow stays on Source when PHP refuses the file, so the reason sits next to the button that asked for it.
  const createDraft = () =>
    importMutation.mutate(session.envelope, {
      onSuccess: (layout) => {
        setCreated(layout);
        setStep('draft');
        toast.success(
          sprintf(
            /* translators: %s: layout title */
            __('Draft "%s" created.', 'yaycheckout'),
            layout.title,
          ),
        );
      },
    });

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
          review={session.review}
          isPending={importMutation.isPending}
          error={importMutation.error}
          onCancel={onClose}
          onImport={createDraft}
        />
      )}
      {step === 'draft' && created && <DraftStep layout={created} onClose={onClose} />}
    </>
  );
}

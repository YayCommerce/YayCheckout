import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { HTTPError } from 'ky';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { markSetupDone } from '@/lib/api/settings.api';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { logger } from '@/lib/logger';
import { invalidateListedLayouts } from '@/lib/queries/layout.queries';
import { useCompleteSetupMutation } from '@/lib/queries/setup.queries';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { StepBreadcrumb } from '@/components/shared/StepBreadcrumb';
import { FinishStep } from './FinishStep';
import { ScanStep } from './ScanStep';
import { StartFromStep } from './StartFromStep';

const STEPS = [__('Scan', 'yaycheckout'), __('Start from', 'yaycheckout')];

/**
 * First-run setup popup, open while `showSetupWizard` is true. The pick stays in
 * memory until Create layout sends it in one save; Skip setup only marks setup
 * done. No close icon, Esc or outside click. Settings reopens it with
 * `RerunSetupWizard`.
 */
export function SetupWizard() {
  return window.yayCheckoutMeta.checkoutMeta.showSetupWizard ? <SetupWizardDialog /> : null;
}

/** The wizard reopened from Settings: Create layout adds another draft, and Skip setup only closes. */
export function RerunSetupWizard({ onClose }: { onClose: () => void }) {
  return <SetupWizardDialog rerun onClose={onClose} />;
}

function SetupWizardDialog({ rerun = false, onClose }: { rerun?: boolean; onClose?: () => void }) {
  const [open, setOpen] = useState(true);
  const [step, setStep] = useState(0);
  const [templateSlug, setTemplateSlug] = useState<string | null>();
  const [applyError, setApplyError] = useState<string | null>(null);
  const queryClient = useQueryClient();
  const setup = useCompleteSetupMutation();
  const draft = setup.data?.layout;
  const isFinished = step === STEPS.length;

  function close() {
    setOpen(false);
    onClose?.();
  }

  function onSkip() {
    close();
    if (!rerun) markSetupDone().catch((e) => logger.warn('Mark setup done failed', e));
  }

  async function onApply() {
    if (!templateSlug) return;
    setApplyError(null);
    try {
      await setup.mutateAsync({ templateSlug, rerun });
      setStep(STEPS.length);
    } catch (e) {
      // 409: an earlier Create layout whose response was lost, or another tab, already finished setup.
      if (e instanceof HTTPError && e.response.status === 409) {
        close();
        invalidateListedLayouts(queryClient);
        toast.info(__('Setup was already completed.', 'yaycheckout'));
        return;
      }
      setApplyError(await getErrorMsg(e));
    }
  }

  return (
    <Dialog open={open}>
      <DialogContent
        className="max-h-[calc(100dvh-2rem)] gap-6 p-8 *:data-[slot=dialog-close]:hidden sm:max-w-220"
        onEscapeKeyDown={(e) => e.preventDefault()}
        onInteractOutside={(e) => e.preventDefault()}
        aria-describedby={undefined}
      >
        <DialogTitle className="sr-only">{__('Set up YayCheckout', 'yaycheckout')}</DialogTitle>

        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <StepBreadcrumb labels={STEPS} current={step} />
            {isFinished && <Badge variant="success-outline">{__('Done', 'yaycheckout')}</Badge>}
          </div>
          {!isFinished && (
            <Button variant="link" size="sm" disabled={setup.isPending} onClick={onSkip}>
              {__('Skip setup', 'yaycheckout')}
            </Button>
          )}
        </div>

        {step === 0 && <ScanStep onContinue={() => setStep(1)} />}
        {step === 1 && (
          <StartFromStep
            templateSlug={templateSlug}
            onTemplateChange={setTemplateSlug}
            isApplying={setup.isPending}
            error={applyError}
            onBack={() => {
              setApplyError(null);
              setStep(0);
            }}
            onApply={onApply}
          />
        )}
        {isFinished && draft && templateSlug && (
          <FinishStep draft={draft} templateSlug={templateSlug} onClose={close} />
        )}
      </DialogContent>
    </Dialog>
  );
}

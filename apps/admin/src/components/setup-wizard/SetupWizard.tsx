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
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { FinishStep } from './FinishStep';
import { StartFromStep } from './StartFromStep';

/**
 * First-run setup popup, open while `showSetupWizard` is true: pick a template,
 * then a confirmation of the draft it created. The pick stays in
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
  const [templateSlug, setTemplateSlug] = useState<string | null>();
  const [applyError, setApplyError] = useState<string | null>(null);
  const queryClient = useQueryClient();
  const setup = useCompleteSetupMutation();
  const draft = setup.data?.layout;

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

        {draft && templateSlug ? (
          <FinishStep draft={draft} templateSlug={templateSlug} onClose={close} />
        ) : (
          <>
            <div className="flex items-start justify-between gap-4">
              <div className="space-y-1">
                <h2 className="text-lg font-semibold">
                  {rerun
                    ? __('Create a layout from a template', 'yaycheckout')
                    : __('Welcome to YayCheckout', 'yaycheckout')}
                </h2>
                <p className="text-muted-foreground text-sm">
                  {rerun
                    ? __(
                        'Pick a template to start from. It is saved as a draft, so your customers keep seeing the current checkout until you publish it.',
                        'yaycheckout',
                      )
                    : __(
                        'Pick a template for your first checkout layout. It is saved as a draft, so your customers keep seeing the WooCommerce checkout until you publish it.',
                        'yaycheckout',
                      )}
                </p>
              </div>
              <Button variant="link" size="sm" disabled={setup.isPending} onClick={onSkip}>
                {__('Skip setup', 'yaycheckout')}
              </Button>
            </div>

            <StartFromStep
              templateSlug={templateSlug}
              onTemplateChange={setTemplateSlug}
              isApplying={setup.isPending}
              error={applyError}
              onApply={onApply}
            />
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

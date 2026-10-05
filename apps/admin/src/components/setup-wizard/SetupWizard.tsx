import { Fragment, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQueryClient } from '@tanstack/react-query';
import { HTTPError } from 'ky';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { markSetupDone } from '@/lib/api/settings.api';
import { activePresetIdFor } from '@/lib/helpers/design.helper';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { logger } from '@/lib/logger';
import { DESIGN_QUERIES, useDesignQuery } from '@/lib/queries/design.queries';
import { invalidateListedLayouts } from '@/lib/queries/layout.queries';
import { useCompleteSetupMutation } from '@/lib/queries/setup.queries';
import { designFormSchema, type DesignForm } from '@/lib/schema/design.schema';
import { Badge } from '@/components/ui/badge';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Form } from '@/components/ui/form';
import { BrandStep } from './BrandStep';
import { FinishStep } from './FinishStep';
import { ScanStep } from './ScanStep';
import { StartFromStep } from './StartFromStep';

const STEPS = [__('Scan', 'yaycheckout'), __('Start from', 'yaycheckout'), __('Brand', 'yaycheckout')];

/**
 * First-run setup popup, open while `showSetupWizard` is true. Picks stay in
 * memory until Apply brand sends them in one save; Skip setup only marks setup
 * done. No close icon, Esc or outside click.
 */
export function SetupWizard() {
  const [open, setOpen] = useState(window.yayCheckoutMeta.checkoutMeta.showSetupWizard);
  const [step, setStep] = useState(0);
  const [templateSlug, setTemplateSlug] = useState<string | null>();
  const [applyError, setApplyError] = useState<string | null>(null);
  const { data: design } = useDesignQuery();
  const queryClient = useQueryClient();
  const setup = useCompleteSetupMutation();
  const brandForm = useForm<DesignForm>({
    resolver: zodResolver(designFormSchema),
    defaultValues: { tokens: design.tokens, customCss: design.customCss },
  });
  const draft = setup.data?.layout;
  const isFinished = step === STEPS.length;

  function onSkip() {
    setOpen(false);
    markSetupDone().catch((e) => logger.warn('Mark setup done failed', e));
  }

  async function onApply(values: DesignForm) {
    if (!templateSlug) return;
    setApplyError(null);
    try {
      await setup.mutateAsync({
        templateSlug,
        form: values,
        presetId: activePresetIdFor(design.presets, design.presetId, values.tokens),
      });
      setStep(STEPS.length);
    } catch (e) {
      // 409: an earlier Apply whose response was lost, or another tab, already finished setup.
      if (e instanceof HTTPError && e.response.status === 409) {
        setOpen(false);
        queryClient.invalidateQueries({ queryKey: DESIGN_QUERIES.main.queryKey });
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
            <Breadcrumb>
              <BreadcrumbList>
                {STEPS.map((label, index) => (
                  <Fragment key={label}>
                    {index > 0 && <BreadcrumbSeparator />}
                    <BreadcrumbItem>
                      {index === step ? <BreadcrumbPage>{label}</BreadcrumbPage> : <span>{label}</span>}
                    </BreadcrumbItem>
                  </Fragment>
                ))}
              </BreadcrumbList>
            </Breadcrumb>
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
            onBack={() => setStep(0)}
            onContinue={() => setStep(2)}
          />
        )}
        {step === 2 && templateSlug && (
          <Form {...brandForm}>
            <BrandStep
              isApplying={setup.isPending}
              error={applyError}
              onBack={() => {
                setApplyError(null);
                setStep(1);
              }}
              onApply={onApply}
            />
          </Form>
        )}
        {isFinished && draft && templateSlug && (
          <FinishStep draft={draft} templateSlug={templateSlug} onClose={() => setOpen(false)} />
        )}
      </DialogContent>
    </Dialog>
  );
}

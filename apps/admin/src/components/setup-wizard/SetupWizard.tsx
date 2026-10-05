import { Fragment, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { markSetupDone } from '@/lib/api/settings.api';
import { activePresetIdFor } from '@/lib/helpers/design.helper';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { logger } from '@/lib/logger';
import { useDesignQuery } from '@/lib/queries/design.queries';
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
 * memory until Apply brand saves them all in one POST /setup, which also marks
 * setup done; a refused save writes nothing, so Back and Skip setup stay
 * honest after it. No close icon, Esc or outside click.
 */
export function SetupWizard() {
  const [open, setOpen] = useState(window.yayCheckoutMeta.checkoutMeta.showSetupWizard);
  const [step, setStep] = useState(0);
  const [templateSlug, setTemplateSlug] = useState<string | null>();
  const [applyError, setApplyError] = useState<string | null>(null);
  const { data: design } = useDesignQuery();
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
              templateSlug={templateSlug}
              isApplying={setup.isPending}
              error={applyError}
              onBack={() => setStep(1)}
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

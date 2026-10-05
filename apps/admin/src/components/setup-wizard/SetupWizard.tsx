import { Fragment, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useIsMutating } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { markSetupDone } from '@/lib/api/settings.api';
import { logger } from '@/lib/logger';
import { useDesignQuery } from '@/lib/queries/design.queries';
import { designFormSchema, type DesignForm } from '@/lib/schema/design.schema';
import type { Layout } from '@/lib/schema/layout.schema';
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
import { BrandStep } from './BrandStep';
import { FinishStep } from './FinishStep';
import { ScanStep } from './ScanStep';
import { StartFromStep } from './StartFromStep';

const STEPS = [__('Scan', 'yaycheckout'), __('Start from', 'yaycheckout'), __('Brand', 'yaycheckout')];

/**
 * First-run setup popup, open while `showSetupWizard` is true. Nothing the
 * merchant picks is stored until Apply brand; Skip setup leaves without saving
 * any of it. No close icon, Esc or outside click.
 */
export function SetupWizard() {
  const [open, setOpen] = useState(window.yayCheckoutMeta.checkoutMeta.showSetupWizard);
  const [step, setStep] = useState(0);
  const [templateSlug, setTemplateSlug] = useState<string | null>();
  const [draft, setDraft] = useState<Layout | null>(null);
  const { data: design } = useDesignQuery();
  const brandForm = useForm<DesignForm>({
    resolver: zodResolver(designFormSchema),
    defaultValues: { tokens: design.tokens, customCss: design.customCss },
  });
  // Apply brand mid-flight: skipping now would close over a half-written design and draft.
  const isBusy =
    useIsMutating({ mutationKey: ['design', 'save'] }) + useIsMutating({ mutationKey: ['layouts', 'create-draft'] }) >
    0;
  const isFinished = step === STEPS.length;

  function markDone() {
    markSetupDone().catch((e) => logger.warn('Mark setup done failed', e));
  }

  function onSkip() {
    setOpen(false);
    markDone();
  }

  function onNext() {
    setStep(step + 1);
    if (step + 1 === STEPS.length) markDone();
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
            <Button variant="link" size="sm" disabled={isBusy} onClick={onSkip}>
              {__('Skip setup', 'yaycheckout')}
            </Button>
          )}
        </div>

        {step === 0 && <ScanStep onContinue={onNext} />}
        {step === 1 && (
          <StartFromStep
            templateSlug={templateSlug}
            onTemplateChange={setTemplateSlug}
            onBack={() => setStep(0)}
            onContinue={onNext}
          />
        )}
        {step === 2 && templateSlug && (
          <BrandStep
            form={brandForm}
            templateSlug={templateSlug}
            onBack={() => setStep(1)}
            onApplied={(created) => {
              setDraft(created);
              onNext();
            }}
          />
        )}
        {isFinished && draft && templateSlug && (
          <FinishStep draft={draft} templateSlug={templateSlug} onClose={() => setOpen(false)} />
        )}
      </DialogContent>
    </Dialog>
  );
}

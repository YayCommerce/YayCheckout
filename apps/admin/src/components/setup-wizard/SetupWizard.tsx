import { Fragment, useState } from 'react';
import { CircleCheck } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { markSetupDone } from '@/lib/api/settings.api';
import { logger } from '@/lib/logger';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { ScanStep } from './ScanStep';

const STEPS = [__('Scan', 'yaycheckout'), __('Start from', 'yaycheckout'), __('Brand', 'yaycheckout')];

/**
 * First-run setup popup, shown on every admin visit until the merchant reaches
 * Finish or skips. Skip setup is the only way out: no close icon, Esc or outside click.
 */
export function SetupWizard() {
  // ponytail: dev-only trigger — opens when arriving from the Plugins screen's Settings link (same URL as the menu, so referrer tells them apart). Swap to `!window.yayCheckoutMeta.checkoutMeta.setupDone` once every step ships.
  const [open, setOpen] = useState(document.referrer.includes('/wp-admin/plugins.php'));
  const [step, setStep] = useState(0);
  const finished = step === STEPS.length;

  async function markDone() {
    try {
      await markSetupDone();
    } catch (e) {
      logger.warn('Mark setup done failed', e);
    }
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
        className="gap-6 p-8 *:data-[slot=dialog-close]:hidden sm:max-w-220"
        onEscapeKeyDown={(e) => e.preventDefault()}
        onInteractOutside={(e) => e.preventDefault()}
        aria-describedby={undefined}
      >
        <DialogTitle className="sr-only">{__('Set up YayCheckout', 'yaycheckout')}</DialogTitle>

        <div className="flex items-center justify-between gap-4">
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
          {finished ? (
            <span className="text-success flex items-center gap-1 text-sm font-medium">
              <CircleCheck className="size-4" />
              {__('Done', 'yaycheckout')}
            </span>
          ) : (
            <Button variant="link" size="sm" onClick={onSkip}>
              {__('Skip setup', 'yaycheckout')}
            </Button>
          )}
        </div>

        {/* Scan owns its footer: its left slot is progress / Re-run scan, never Back. */}
        {step === 0 && <ScanStep onContinue={onNext} />}
        {step > 0 && <div className="min-h-80 rounded-sm border border-dashed" />}

        {step === 0 ? null : finished ? (
          <div className="flex justify-end gap-2">
            <Button onClick={() => setOpen(false)}>{__('Go to Layouts', 'yaycheckout')}</Button>
          </div>
        ) : (
          <div className="flex justify-between gap-2">
            <Button variant="outline" onClick={() => setStep(step - 1)}>
              {__('Back', 'yaycheckout')}
            </Button>
            <Button onClick={onNext}>
              {step === STEPS.length - 1 ? __('Apply brand', 'yaycheckout') : __('Continue', 'yaycheckout')}
            </Button>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

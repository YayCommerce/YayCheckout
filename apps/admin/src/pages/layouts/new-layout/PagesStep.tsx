import type { ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { NewLayoutFormValues } from '@/lib/schema/layout.schema';
import type { Template } from '@/lib/schema/template.schema';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { isPro } from '@/lib/plan';
import { ProBadge } from '@/components/pro/ProGate';
import { LockedOverlay } from '@/components/shared/template-picker/TemplatePicker';
import { TemplatePreview } from '@/components/shared/template-picker/TemplatePreview';

/**
 * The pages the new layout comes with, previewed side by side: its checkout, thank-you and pay-order pages.
 * In Pro, Create layout makes all three; the server writes the thank-you and pay-order pages from the template.
 * Those two pages are Pro, so Free shows their cards locked, an upsell only: Free's server makes the checkout alone.
 */
export function PagesStep({
  template,
  onBack,
  isPending,
  rootError,
}: {
  template: Template;
  onBack: () => void;
  isPending: boolean;
  rootError?: string;
}) {
  const { control } = useFormContext<NewLayoutFormValues>();
  const name = useWatch<NewLayoutFormValues, 'name'>({ control, name: 'name' });

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col gap-1">
        <h3 className="text-sm font-medium">{__('Preview pages', 'yaycheckout')}</h3>
        <p className="text-muted-foreground text-sm">{name}</p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <PageCard title={__('Checkout', 'yaycheckout')}>
          <TemplatePreview template={template} />
        </PageCard>
        <PageCard
          title={__('Thank you', 'yaycheckout')}
          hint={isPro ? undefined : __('Upgrade to Pro to customize the thank-you page', 'yaycheckout')}
        >
          <TemplatePreview
            template={template}
            previewUrl={template.thankYouPreviewUrl}
            placeholder={__('Preview coming soon', 'yaycheckout')}
          >
            {!isPro && (
              <>
                <LockedOverlay />
                <ProBadge className="absolute top-2 right-2 shadow-sm" />
              </>
            )}
          </TemplatePreview>
        </PageCard>
        <PageCard
          title={__('Pay order', 'yaycheckout')}
          hint={isPro ? undefined : __('Upgrade to Pro to customize the pay-order page', 'yaycheckout')}
        >
          <TemplatePreview
            template={template}
            previewUrl={template.payOrderPreviewUrl}
            placeholder={__('Preview coming soon', 'yaycheckout')}
          >
            {!isPro && (
              <>
                <LockedOverlay />
                <ProBadge className="absolute top-2 right-2 shadow-sm" />
              </>
            )}
          </TemplatePreview>
        </PageCard>
      </div>

      {rootError && <p className="text-destructive text-sm">{rootError}</p>}

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-end">
        <div className="flex gap-2">
          <Button type="button" variant="outline" disabled={isPending} onClick={onBack}>
            {__('Back', 'yaycheckout')}
          </Button>
          <Button type="submit" disabled={isPending}>
            {isPending ? __('Creating…', 'yaycheckout') : __('Create layout', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </div>
  );
}

/** Not selectable: a group, focusable so its preview still scrolls by keyboard as the picker's tiles do. */
function PageCard({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
  return (
    <div
      role="group"
      tabIndex={0}
      title={hint}
      aria-label={title}
      className="group flex flex-col items-start gap-2 rounded-md border p-2 outline-none focus-visible:ring-2"
    >
      {children}
      <span className="text-xs font-medium">{title}</span>
    </div>
  );
}

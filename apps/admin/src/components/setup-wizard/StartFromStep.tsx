import { useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { isPickable, useTemplate, useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import { Button, LoadingButton } from '@/components/ui/button';
import { TemplatePicker } from '@/components/shared/template-picker/TemplatePicker';

/**
 * Wizard step 2: pick the template the draft starts from. Create layout sends
 * the pick in the wizard's one save, which creates the draft.
 *
 * @param templateSlug undefined until first defaulted, null when a filter cleared it.
 */
export function StartFromStep({
  templateSlug,
  onTemplateChange,
  isApplying,
  error,
  onBack,
  onApply,
}: {
  templateSlug: string | null | undefined;
  onTemplateChange: (slug: string | null) => void;
  isApplying: boolean;
  error: string | null;
  onBack: () => void;
  onApply: () => void;
}) {
  const { data } = useTemplateLibraryQuery();
  const firstPickable = data?.templates.find(isPickable);

  useEffect(() => {
    if (templateSlug === undefined && firstPickable) onTemplateChange(firstPickable.slug);
  }, [templateSlug, firstPickable, onTemplateChange]);

  const selected = useTemplate(templateSlug);

  return (
    <div className="flex flex-col gap-6">
      <h2 className="text-lg font-semibold">{__('Where should your layout start?', 'yaycheckout')}</h2>

      <TemplatePicker selectedSlug={templateSlug} onSelect={onTemplateChange} />

      {error && <p className="text-destructive text-sm">{error}</p>}

      <div className="flex items-center justify-between gap-2 border-t pt-4">
        <Button variant="outline" onClick={onBack} disabled={isApplying}>
          {__('Back', 'yaycheckout')}
        </Button>
        <div className="flex items-center gap-3">
          {selected && (
            <span className="text-muted-foreground text-sm">
              {sprintf(
                /* translators: %s: template name */
                __('Selected: %s', 'yaycheckout'),
                selected.name,
              )}
            </span>
          )}
          <LoadingButton loading={isApplying} disabled={!selected || isApplying} onClick={onApply}>
            {__('Create layout', 'yaycheckout')}
          </LoadingButton>
        </div>
      </div>
    </div>
  );
}

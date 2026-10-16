import { useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { isPickable, useTemplate, useTemplates } from '@/lib/queries/template.queries';
import { LoadingButton } from '@/components/ui/button';
import { TemplatePicker } from '@/components/shared/template-picker/TemplatePicker';

/**
 * The wizard's template step: pick the template the draft starts from. Create layout sends
 * the pick in the wizard's one save, which creates the draft.
 *
 * @param templateSlug undefined until first defaulted, null when a filter cleared it.
 */
export function StartFromStep({
  templateSlug,
  onTemplateChange,
  isApplying,
  error,
  onApply,
}: {
  templateSlug: string | null | undefined;
  onTemplateChange: (slug: string | null) => void;
  isApplying: boolean;
  error: string | null;
  onApply: () => void;
}) {
  const firstPickable = useTemplates().find(isPickable);

  useEffect(() => {
    if (templateSlug === undefined && firstPickable) onTemplateChange(firstPickable.slug);
  }, [templateSlug, firstPickable, onTemplateChange]);

  const selected = useTemplate(templateSlug);

  return (
    <div className="flex flex-col gap-6">
      <TemplatePicker selectedSlug={templateSlug} onSelect={onTemplateChange} />

      {error && <p className="text-destructive text-sm">{error}</p>}

      <div className="flex items-center justify-end gap-2 border-t pt-4">
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

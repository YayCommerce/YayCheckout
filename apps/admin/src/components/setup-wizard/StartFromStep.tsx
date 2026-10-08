import { useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { useTemplate, useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import { Button } from '@/components/ui/button';
import { TemplatePicker } from '@/components/shared/template-picker/TemplatePicker';

/**
 * Wizard step 2: pick the template the draft starts from. Only remembers the
 * pick; the draft is created when Brand is applied.
 *
 * @param templateSlug undefined until first defaulted, null when a filter cleared it.
 */
export function StartFromStep({
  templateSlug,
  onTemplateChange,
  onBack,
  onContinue,
}: {
  templateSlug: string | null | undefined;
  onTemplateChange: (slug: string | null) => void;
  onBack: () => void;
  onContinue: () => void;
}) {
  const { data } = useTemplateLibraryQuery();
  const firstUnlocked = data?.templates.find((t) => !t.locked);

  useEffect(() => {
    if (templateSlug === undefined && firstUnlocked) onTemplateChange(firstUnlocked.slug);
  }, [templateSlug, firstUnlocked, onTemplateChange]);

  const selected = useTemplate(templateSlug);

  return (
    <div className="flex flex-col gap-6">
      <h2 className="text-lg font-semibold">{__('Where should your layout start?', 'yaycheckout')}</h2>

      <TemplatePicker selectedSlug={templateSlug} onSelect={onTemplateChange} />

      <div className="flex items-center justify-between gap-2 border-t pt-4">
        <Button variant="outline" onClick={onBack}>
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
          <Button disabled={!selected} onClick={onContinue}>
            {__('Continue', 'yaycheckout')}
          </Button>
        </div>
      </div>
    </div>
  );
}

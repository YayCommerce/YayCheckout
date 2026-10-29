import { Check } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { useTemplate } from '@/lib/queries/template.queries';
import type { Layout } from '@/lib/schema/layout.schema';
import { LayoutDraftActions } from '@/components/shared/LayoutDraftActions';
import { TemplatePreview } from '@/components/shared/template-picker/TemplatePreview';

/** Wizard confirmation: the draft Start from created, and where to take it next. Nothing is live yet. */
export function FinishStep({
  draft,
  templateSlug,
  onClose,
}: {
  draft: Layout;
  templateSlug: string;
  onClose: () => void;
}) {
  const template = useTemplate(templateSlug);

  return (
    <div className="mx-auto flex max-w-xl flex-col items-center gap-6 py-4 text-center">
      <span className="bg-success/10 text-success flex size-14 items-center justify-center rounded-full">
        <Check className="size-7" />
      </span>

      <div className="space-y-2">
        <h2 className="text-xl font-semibold">{__('Your layout is ready', 'yaycheckout')}</h2>
        <p className="text-muted-foreground text-sm">
          {sprintf(
            /* translators: %s: layout name */
            __(
              '"%s" was created as a draft. Nothing is live yet: publish it from the editor when you are happy.',
              'yaycheckout',
            ),
            draft.title,
          )}
        </p>
      </div>

      {template && (
        <div className="group w-72">
          <TemplatePreview template={template} />
        </div>
      )}

      <LayoutDraftActions layoutId={draft.id} onClose={onClose} />
    </div>
  );
}

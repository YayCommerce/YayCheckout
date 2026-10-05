import { Check } from 'lucide-react';
import { useNavigate } from 'react-router';
import { __, sprintf } from '@wordpress/i18n';

import { useCompatibilityQuery } from '@/lib/queries/compatibility.queries';
import { useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import type { Layout } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { SummaryStrip } from '@/components/shared/SummaryStrip';

/** Wizard confirmation: the draft Apply brand created, and where to take it next. Nothing is live yet. */
export function FinishStep({
  draft,
  templateSlug,
  onClose,
}: {
  draft: Layout;
  templateSlug: string;
  onClose: () => void;
}) {
  const navigate = useNavigate();
  const { data: library } = useTemplateLibraryQuery();
  const { data: report } = useCompatibilityQuery();

  const templateName = library?.templates.find((t) => t.slug === templateSlug)?.name ?? templateSlug;
  const storeType =
    report?.store.storeType === 'block' ? __('Block checkout', 'yaycheckout') : __('Classic store', 'yaycheckout');

  function goTo(path: string) {
    onClose();
    navigate(path);
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex gap-4">
        <span className="bg-success/10 text-success flex size-10 shrink-0 items-center justify-center rounded-md">
          <Check className="size-5" />
        </span>
        <div className="space-y-1">
          <h2 className="text-lg font-semibold">{__('Your draft is ready. Nothing is live yet.', 'yaycheckout')}</h2>
          <p className="text-muted-foreground text-sm">
            {sprintf(
              /* translators: 1: layout name, 2: template name */
              __(
                'One layout, "%1$s", built from %2$s. Your customers still see the native WooCommerce checkout.',
                'yaycheckout',
              ),
              draft.title,
              templateName,
            )}
          </p>
        </div>
      </div>

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), draft.title],
          [__('Started from', 'yaycheckout'), templateName],
          [__('Renders on', 'yaycheckout'), storeType],
          [__('Status', 'yaycheckout'), __('Draft', 'yaycheckout')],
        ]}
      />

      <div className="flex items-center justify-between gap-2 border-t pt-4">
        <p className="text-muted-foreground text-sm">
          {__('Publish from the editor when you are happy.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => goTo('/layouts')}>
            {__('Go to Layouts', 'yaycheckout')}
          </Button>
          <Button onClick={() => goTo(`/layouts?edit=${draft.id}`)}>{__('Open in editor', 'yaycheckout')}</Button>
        </div>
      </div>
    </div>
  );
}

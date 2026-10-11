import { __, sprintf } from '@wordpress/i18n';

import { storeTypeLabel } from '@/lib/helpers/compatibility.helper';
import { useCompatibilityQuery } from '@/lib/queries/compatibility.queries';
import { useTemplate } from '@/lib/queries/template.queries';
import type { Layout } from '@/lib/schema/layout.schema';
import { LayoutDraftActions } from '@/components/shared/LayoutDraftActions';
import { SuccessHeader } from '@/components/shared/SuccessHeader';
import { SummaryStrip } from '@/components/shared/SummaryStrip';

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
  const { data: report } = useCompatibilityQuery();

  const templateName = useTemplate(templateSlug)?.name ?? templateSlug;

  return (
    <div className="flex flex-col gap-6">
      <SuccessHeader
        title={__('Your layout is ready. Nothing is live yet.', 'yaycheckout')}
        description={sprintf(
          /* translators: 1: layout name, 2: template name */
          __(
            'One layout, "%1$s", built from %2$s. Your customers still see the native WooCommerce checkout.',
            'yaycheckout',
          ),
          draft.title,
          templateName,
        )}
        size="lg"
      />

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), draft.title],
          [__('Started from', 'yaycheckout'), templateName],
          [__('Renders on', 'yaycheckout'), report ? storeTypeLabel(report.store.storeType) : '—'],
          [__('Status', 'yaycheckout'), __('Inactive', 'yaycheckout')],
        ]}
      />

      <div className="flex items-center justify-between gap-2 border-t pt-4">
        <p className="text-muted-foreground text-sm">
          {__('Publish from the editor when you are happy.', 'yaycheckout')}
        </p>
        <LayoutDraftActions layoutId={draft.id} onClose={onClose} />
      </div>
    </div>
  );
}

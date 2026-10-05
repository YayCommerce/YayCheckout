import { Check, TriangleAlert } from 'lucide-react';
import { useNavigate } from 'react-router';
import { __, sprintf } from '@wordpress/i18n';

import { editLayoutPath } from '@/lib/helpers/layout.helper';
import type { Layout } from '@/lib/schema/layout.schema';
import type { RulesImport } from '@/lib/schema/tools.schema';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { SummaryStrip } from '@/components/shared/SummaryStrip';

export function DraftStep({
  layout,
  rulesImport,
  onClose,
}: {
  layout: Layout;
  rulesImport: RulesImport;
  onClose: () => void;
}) {
  const navigate = useNavigate();

  const go = (path: string) => {
    onClose();
    navigate(path);
  };

  return (
    <>
      <div className="flex gap-3">
        <span className="bg-success/10 text-success flex size-8 shrink-0 items-center justify-center rounded-full">
          <Check className="size-4" />
        </span>
        <div className="space-y-1">
          <h3 className="text-base/snug font-semibold">
            {sprintf(
              /* translators: %s: layout title */
              __('Draft "%s" created — nothing is live', 'yaycheckout'),
              layout.title,
            )}
          </h3>
          <p className="text-muted-foreground text-sm">
            {__('Your active layout is still serving checkout.', 'yaycheckout')}
          </p>
        </div>
      </div>

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), layout.title],
          [__('Status', 'yaycheckout'), __('Draft', 'yaycheckout')],
          [__('Assignment rules', 'yaycheckout'), RULES_STATUS_LABELS[rulesImport.status]],
        ]}
      />

      {rulesImport.status === 'dropped' && (
        <Alert variant="outline" className="border-warning/30 bg-warning/5">
          <TriangleAlert />
          <AlertDescription>
            {__(
              'The file’s assignment rules could not be read by this version, so the draft has none. Add them again in Assignment.',
              'yaycheckout',
            )}
          </AlertDescription>
        </Alert>
      )}

      <DialogFooter className="items-center border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-sm">
          {__('Publish and set active when you are happy with it.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => go('/layouts')}>
            {__('Go to Layouts', 'yaycheckout')}
          </Button>
          {/* Same embedded editor the Edit action uses. */}
          <Button onClick={() => go(editLayoutPath(layout.id))}>{__('Open in editor', 'yaycheckout')}</Button>
        </div>
      </DialogFooter>
    </>
  );
}

const RULES_STATUS_LABELS: Record<RulesImport['status'], string> = {
  imported: __('Imported', 'yaycheckout'),
  dropped: __('Not imported', 'yaycheckout'),
  none: __('None in the file', 'yaycheckout'),
};

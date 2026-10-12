import { Info } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { LayoutConfirmDialog, type LayoutConfirmProps } from '@/pages/layouts/LayoutConfirmDialog';

/**
 * Confirms moving a layout to the archive; reversible, so the button is not destructive.
 * Never opens for the active row: its Archive item is disabled, matching LayoutActiveGuard.
 */
export function ArchiveModal(props: LayoutConfirmProps) {
  return (
    <LayoutConfirmDialog
      {...props}
      title={(layout) =>
        sprintf(
          /* translators: %s: layout title */
          __('Archive "%s"?', 'yaycheckout'),
          layout.title,
        )
      }
      body={(layout) => (
        <>
          <p className="text-muted-foreground text-sm">
            {sprintf(
              /* translators: %s: layout title */
              __('"%s" moves out of the list into the Archive tab. Its blocks and revisions are kept.', 'yaycheckout'),
              layout.title,
            )}
          </p>

          <Alert variant="muted">
            <Info />
            <AlertDescription>
              {__('Restore it any time from the Archive tab. Archiving is not deleting.', 'yaycheckout')}
            </AlertDescription>
          </Alert>
        </>
      )}
      confirmLabel={__('Archive', 'yaycheckout')}
      pendingLabel={__('Archiving…', 'yaycheckout')}
    />
  );
}

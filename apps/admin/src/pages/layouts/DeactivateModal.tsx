import { TriangleAlert } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { LayoutConfirmDialog, type LayoutConfirmProps } from '@/pages/layouts/LayoutConfirmDialog';

/** Warns that deactivating leaves no layout active; the bulk Deactivate shows it too. */
export function NoActiveLayoutAlert() {
  return (
    <Alert variant="muted" destructive>
      <TriangleAlert />
      <AlertDescription>
        {__(
          'No layout will be active. Your checkout page falls back to the default WooCommerce checkout.',
          'yaycheckout',
        )}
      </AlertDescription>
    </Alert>
  );
}

/** Confirms taking a layout off the storefront. It goes back to draft, which merchants see as Inactive. */
export function DeactivateModal({
  isLastActive,
  ...props
}: LayoutConfirmProps & {
  /** No layout would remain active. */
  isLastActive: boolean;
}) {
  return (
    <LayoutConfirmDialog
      {...props}
      title={(layout) =>
        sprintf(
          /* translators: %s: layout title */
          __('Deactivate "%s"?', 'yaycheckout'),
          layout.title,
        )
      }
      body={() => (
        <>
          <p className="text-muted-foreground text-sm">
            {__(
              'Shoppers will stop seeing this layout. It becomes inactive, and you can activate it again at any time.',
              'yaycheckout',
            )}
          </p>

          {isLastActive && <NoActiveLayoutAlert />}
        </>
      )}
      confirmLabel={__('Deactivate', 'yaycheckout')}
      pendingLabel={__('Deactivating…', 'yaycheckout')}
      destructive
    />
  );
}

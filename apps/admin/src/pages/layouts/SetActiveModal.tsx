import { __, sprintf } from '@wordpress/i18n';

import { LayoutConfirmDialog, type LayoutConfirmProps } from '@/pages/layouts/LayoutConfirmDialog';

export function SetActiveModal(props: LayoutConfirmProps) {
  return (
    <LayoutConfirmDialog
      {...props}
      title={(layout) =>
        sprintf(
          /* translators: %s: layout title */
          __('Make "%s" your live checkout?', 'yaycheckout'),
          layout.title,
        )
      }
      body={() => (
        <p className="text-muted-foreground text-sm">
          {__('Shoppers will start seeing this layout as soon as you confirm.', 'yaycheckout')}
        </p>
      )}
      confirmLabel={__('Activate', 'yaycheckout')}
      pendingLabel={__('Activating…', 'yaycheckout')}
    />
  );
}

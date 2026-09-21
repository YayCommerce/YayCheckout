import { __ } from '@wordpress/i18n';

import { Button, LoadingButton } from '@/components/ui/button';

/** Top-bar Reset/Save. Inert until the form lands; the status line goes in the empty span. */
export function DesignActions() {
  return (
    <>
      <span className="text-muted-foreground font-mono text-xs">{__('not saved yet', 'yaycheckout')}</span>
      <Button type="button" variant="link" size="sm" disabled>
        {__('Reset to theme', 'yaycheckout')}
      </Button>
      <LoadingButton type="button" loading={false} disabled>
        {__('Save', 'yaycheckout')}
      </LoadingButton>
    </>
  );
}

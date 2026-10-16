import { __ } from '@wordpress/i18n';

/** A compatibility check whose detection failed. */
export function NotDetected() {
  return <span className="text-muted-foreground">{__('Not detected', 'yaycheckout')}</span>;
}

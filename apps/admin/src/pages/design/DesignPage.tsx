import { __ } from '@wordpress/i18n';

import PagePlaceholder from '@/pages/PagePlaceholder';

export default function DesignPage() {
  return <PagePlaceholder title={__('Design', 'yaycheckout')} description={__('Customize checkout colors, typography and spacing.', 'yaycheckout')} />;
}

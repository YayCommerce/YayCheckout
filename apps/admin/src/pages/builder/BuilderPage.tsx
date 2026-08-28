import { __ } from '@wordpress/i18n';

import PagePlaceholder from '@/pages/PagePlaceholder';

export default function BuilderPage() {
  return <PagePlaceholder title={__('Builder', 'yaycheckout')} description={__('Visually arrange the checkout layout, sections and steps.', 'yaycheckout')} />;
}

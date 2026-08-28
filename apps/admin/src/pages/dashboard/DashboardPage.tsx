import { __ } from '@wordpress/i18n';

import PagePlaceholder from '@/pages/PagePlaceholder';

export default function DashboardPage() {
  return <PagePlaceholder title={__('Dashboard', 'yaycheckout')} description={__('Overview of checkout performance and recent changes.', 'yaycheckout')} />;
}

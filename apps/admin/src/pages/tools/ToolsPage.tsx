import { __ } from '@wordpress/i18n';

import { Card } from '@/components/ui/card';
import PageLayout from '@/pages/PageLayout';

export default function ToolsPage() {
  return (
    <PageLayout
      heading={__('Tools', 'yaycheckout')}
      description={__('Import, export and maintenance utilities for your checkout.', 'yaycheckout')}
    >
      <Card>
        <p className="text-muted-foreground text-sm">{__('This section is not implemented yet.', 'yaycheckout')}</p>
      </Card>
    </PageLayout>
  );
}

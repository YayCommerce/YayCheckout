import { __ } from '@wordpress/i18n';

import { Card } from '@/components/ui/card';
import PageLayout from '@/pages/PageLayout';

export default function CompatibilityPage() {
  return (
    <PageLayout
      heading={__('Compatibility', 'yaycheckout')}
      description={__('Third-party plugin and theme integrations for the checkout.', 'yaycheckout')}
    >
      <Card>
        <p className="text-muted-foreground text-sm">{__('This section is not implemented yet.', 'yaycheckout')}</p>
      </Card>
    </PageLayout>
  );
}

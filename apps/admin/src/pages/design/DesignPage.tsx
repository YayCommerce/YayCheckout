import { __ } from '@wordpress/i18n';

import { Card } from '@/components/ui/card';
import PageLayout from '@/pages/PageLayout';

export default function DesignPage() {
  return (
    <PageLayout
      heading={__('Design', 'yaycheckout')}
      description={__('Customize checkout colors, typography and spacing.', 'yaycheckout')}
    >
      <Card>
        <p className="text-muted-foreground text-sm">{__('This section is not implemented yet.', 'yaycheckout')}</p>
      </Card>
    </PageLayout>
  );
}

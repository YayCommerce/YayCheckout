import { useNavigate, useParams } from 'react-router';
import { __ } from '@wordpress/i18n';

import { Card } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import PageLayout from '@/pages/PageLayout';

const tabs = [
  { value: 'general', label: __('General', 'yaycheckout') },
  { value: 'emails', label: __('Emails', 'yaycheckout') },
  { value: 'advanced', label: __('Advanced', 'yaycheckout') },
];

export default function SettingsPage() {
  const { subMenu = 'general' } = useParams();
  const navigate = useNavigate();

  return (
    <PageLayout
      heading={__('Settings', 'yaycheckout')}
      description={__('Configure checkout behaviour, emails and advanced options.', 'yaycheckout')}
    >
      <Tabs value={subMenu} onValueChange={(value) => navigate(`/settings/${value}`)}>
        <TabsList>
          {tabs.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value}>
              {tab.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      <Card>
        <p className="text-muted-foreground text-sm">
          {__('Settings for this section are not implemented yet.', 'yaycheckout')}
        </p>
      </Card>
    </PageLayout>
  );
}

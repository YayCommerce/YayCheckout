import { useState } from 'react';
import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { Settings } from '@/lib/schema/settings.schema';
import { Button } from '@/components/ui/button';
import { FormControl, FormField } from '@/components/ui/form';
import { Switch } from '@/components/ui/switch';
import { RerunSetupWizard } from '@/components/setup-wizard/SetupWizard';
import { SettingRow } from './SettingRow';
import { SettingsSection } from './SettingsSection';

// Safe mode and share usage data stay in the schema but are hidden from merchants for now.
export function GeneralTab() {
  const { control } = useFormContext<Settings>();
  // Unmounted on close, so each re-run starts again at the template step.
  const [isRerunOpen, setIsRerunOpen] = useState(false);

  return (
    <>
      <SettingsSection
        title={__('Data and privacy', 'yaycheckout')}
        description={__('What happens to your data when YayCheckout is deleted.', 'yaycheckout')}
      >
        <FormField
          control={control}
          name="general.deleteDataOnUninstall"
          render={({ field }) => (
            <SettingRow
              label={__('Delete all data on uninstall', 'yaycheckout')}
              description={__(
                'Removes layouts, tokens and settings when the plugin is deleted. Off by default.',
                'yaycheckout',
              )}
            >
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
            </SettingRow>
          )}
        />
      </SettingsSection>

      <SettingsSection
        title={__('Setup wizard', 'yaycheckout')}
        description={__(
          'Start a new draft from a template. Your other layouts are not changed.',
          'yaycheckout',
        )}
        action={
          <Button type="button" variant="outline" size="sm" onClick={() => setIsRerunOpen(true)}>
            {__('Re-run setup wizard', 'yaycheckout')}
          </Button>
        }
      />
      {isRerunOpen && <RerunSetupWizard onClose={() => setIsRerunOpen(false)} />}
    </>
  );
}

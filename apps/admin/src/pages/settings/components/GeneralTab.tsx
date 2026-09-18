import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { Settings } from '@/lib/schema/settings.schema';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form';
import { Switch } from '@/components/ui/switch';
import { DocLink } from './DocLink';
import { SettingRow } from './SettingRow';

// Only uninstall deletion has a consumer yet; safe mode and telemetry are stored, unused.
export function GeneralTab() {
  const { control } = useFormContext<Settings>();

  return (
    <div>
      <FormField
        control={control}
        name="general.safeMode"
        render={({ field }) => (
          <SettingRow
            title={__('Safe mode', 'yaycheckout')}
            description={__('Falls back to the native WooCommerce checkout. Your layouts stay saved.', 'yaycheckout')}
          >
            <Switch checked={field.value} onCheckedChange={field.onChange} />
          </SettingRow>
        )}
      />

      <FormField
        control={control}
        name="general.shareUsageData"
        render={({ field }) => (
          <SettingRow
            title={__('Share anonymous usage data', 'yaycheckout')}
            description={
              <>
                {__(
                  'Which blocks you use, WooCommerce and theme versions, and self-test results. No orders, customers, store URL or licence data.',
                  'yaycheckout',
                )}{' '}
                <DocLink href={window.yayCheckoutMeta.checkoutMeta.docsUrl}>
                  {__('What is sent', 'yaycheckout')}
                </DocLink>
              </>
            }
          >
            <Switch checked={field.value} onCheckedChange={field.onChange} />
          </SettingRow>
        )}
      />

      <FormField
        control={control}
        name="general.deleteDataOnUninstall"
        render={({ field }) => (
          <SettingRow
            title={__('Delete all data on uninstall', 'yaycheckout')}
            description={__(
              'Removes layouts, tokens and settings when the plugin is deleted. Off by default.',
              'yaycheckout',
            )}
          >
            <Switch checked={field.value} onCheckedChange={field.onChange} />
          </SettingRow>
        )}
      />

      <SettingRow
        title={__('Setup wizard', 'yaycheckout')}
        description={__('Re-scan your store and start a new draft. Nothing live changes.', 'yaycheckout')}
      >
        <Button type="button" variant="outline" size="sm">
          {__('Re-run setup wizard', 'yaycheckout')}
        </Button>
      </SettingRow>
    </div>
  );
}

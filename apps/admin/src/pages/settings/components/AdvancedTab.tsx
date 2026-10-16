import { ExternalLink } from 'lucide-react';
import { useFormContext } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
  CUSTOM_CSS_MAX_BYTES,
  PREVIEW_LINK_LIFETIME_OPTIONS,
  type PreviewLinkLifetime,
  type Settings,
} from '@/lib/schema/settings.schema';
import { FormControl, FormField } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DocLink } from './DocLink';
import { SettingActionRow, SettingRow } from './SettingRow';
import { SettingsSection } from './SettingsSection';

/** WooCommerce → Status → Logs, filtered to Helpers\Logger's source. */
const LOGS_URL = `${window.yayCheckoutMeta.wpMeta.adminUrl}admin.php?page=wc-status&tab=logs&source=yaycheckout`;

function lifetimeLabel(minutes: PreviewLinkLifetime): string {
  if (minutes % 60 === 0 && minutes >= 60) {
    const hours = minutes / 60;
    /* translators: %d: number of hours */
    return sprintf(_n('%d hour', '%d hours', hours, 'yaycheckout'), hours);
  }
  /* translators: %d: number of minutes */
  return sprintf(_n('%d minute', '%d minutes', minutes, 'yaycheckout'), minutes);
}

// Force-enqueue classic checkout scripts stays in the schema but is hidden from merchants for now.
export function AdvancedTab() {
  const { control } = useFormContext<Settings>();

  return (
    <>
      <SettingsSection
        title={__('Troubleshooting', 'yaycheckout')}
        description={__('Tools for when a checkout does not render or behave as expected.', 'yaycheckout')}
      >
        <SettingActionRow
          title={__('Debug log', 'yaycheckout')}
          description={__('Records render errors and self-test runs.', 'yaycheckout')}
        >
          <DocLink href={LOGS_URL} className="inline-flex items-center gap-1 text-sm">
            {__('Open logs', 'yaycheckout')}
            <ExternalLink className="size-3.5" />
          </DocLink>
        </SettingActionRow>
      </SettingsSection>

      <SettingsSection
        title={__('Preview links', 'yaycheckout')}
        description={__('Links that let someone see a layout before it goes live.', 'yaycheckout')}
      >
        <FormField
          control={control}
          name="advanced.previewLinkLifetime"
          render={({ field }) => (
            <SettingRow
              orientation="vertical"
              label={__('Preview link lifetime', 'yaycheckout')}
              description={__(
                'How long a shared preview URL keeps working. Applies to links created from now on.',
                'yaycheckout',
              )}
            >
              <Select value={String(field.value)} onValueChange={(value) => field.onChange(Number(value))}>
                <FormControl>
                  <SelectTrigger size="sm" className="w-36!">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {PREVIEW_LINK_LIFETIME_OPTIONS.map((minutes) => (
                    <SelectItem key={minutes} value={String(minutes)}>
                      {lifetimeLabel(minutes)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </SettingRow>
          )}
        />
      </SettingsSection>

      <SettingsSection
        title={__('Custom CSS', 'yaycheckout')}
        description={__('Store-wide styles for every checkout layout.', 'yaycheckout')}
      >
        <FormField
          control={control}
          name="advanced.customCss"
          render={({ field }) => (
            <SettingRow
              orientation="vertical"
              label={__('CSS', 'yaycheckout')}
              description={__(
                "Printed on every checkout, before each layout's own custom CSS. Not shown in the editor.",
                'yaycheckout',
              )}
            >
              <FormControl>
                <Textarea
                  {...field}
                  rows={8}
                  maxLength={CUSTOM_CSS_MAX_BYTES}
                  placeholder=".yaycheckout-checkout { }"
                  spellCheck={false}
                  className="w-full! max-w-2xl font-mono text-xs md:text-xs"
                />
              </FormControl>
            </SettingRow>
          )}
        />
      </SettingsSection>
    </>
  );
}

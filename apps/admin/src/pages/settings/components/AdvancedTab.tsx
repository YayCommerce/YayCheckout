import { ExternalLink } from 'lucide-react';
import { useFormContext } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { PREVIEW_LINK_LIFETIME_OPTIONS, type PreviewLinkLifetime, type Settings } from '@/lib/schema/settings.schema';
import { FormField } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { DocLink } from './DocLink';
import { SettingRow } from './SettingRow';

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

/*
 * Support escape hatches. Preview link lifetime is stored only until preview
 * links exist; force-enqueue is consumed by Renderer\CheckoutScripts and is
 * the one setting here that can break a working store, hence the warning.
 */
export function AdvancedTab() {
  const { control } = useFormContext<Settings>();

  return (
    <div>
      <SettingRow
        title={__('Debug log', 'yaycheckout')}
        description={__('Records render errors and self-test runs.', 'yaycheckout')}
      >
        <DocLink href={LOGS_URL} className="inline-flex items-center gap-1 text-sm">
          {__('Open logs', 'yaycheckout')}
          <ExternalLink className="size-3.5" />
        </DocLink>
      </SettingRow>

      <FormField
        control={control}
        name="advanced.previewLinkLifetime"
        render={({ field }) => (
          <SettingRow
            title={__('Preview link lifetime', 'yaycheckout')}
            description={__('How long a shared preview URL keeps working.', 'yaycheckout')}
          >
            <Select value={String(field.value)} onValueChange={(value) => field.onChange(Number(value))}>
              <SelectTrigger size="sm" className="w-36">
                <SelectValue />
              </SelectTrigger>
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

      <FormField
        control={control}
        name="advanced.forceEnqueueCheckoutScripts"
        render={({ field }) => (
          <SettingRow
            title={__('Force-enqueue classic checkout scripts', 'yaycheckout')}
            description={__(
              'Loads wc-checkout and wc-country-select even when WooCommerce decides they are not needed.',
              'yaycheckout',
            )}
            warning={__(
              'Only turn this on if the self-test reports missing scripts. It can conflict with themes that enqueue them already.',
              'yaycheckout',
            )}
          >
            <Switch checked={field.value} onCheckedChange={field.onChange} />
          </SettingRow>
        )}
      />
    </div>
  );
}

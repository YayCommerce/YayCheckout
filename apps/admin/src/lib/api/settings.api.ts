import { api } from '@/lib/api/api';
import {
  Integration,
  IntegrationWire,
  Settings,
  SettingsResponse,
  SettingsResponseWire,
  settingsResponseWireSchema,
  SettingsWire,
} from '@/lib/schema/settings.schema';

export function settingsFromWire(wire: SettingsWire): Settings {
  return {
    general: {
      safeMode: wire.general.safe_mode,
      shareUsageData: wire.general.share_usage_data,
      deleteDataOnUninstall: wire.general.delete_data_on_uninstall,
    },
    advanced: {
      previewLinkLifetime: wire.advanced.preview_link_lifetime,
      forceEnqueueCheckoutScripts: wire.advanced.force_enqueue_checkout_scripts,
      customCss: wire.advanced.custom_css,
    },
  };
}

export function settingsToWire(settings: Settings): SettingsWire {
  return {
    general: {
      safe_mode: settings.general.safeMode,
      share_usage_data: settings.general.shareUsageData,
      delete_data_on_uninstall: settings.general.deleteDataOnUninstall,
    },
    advanced: {
      preview_link_lifetime: settings.advanced.previewLinkLifetime,
      force_enqueue_checkout_scripts: settings.advanced.forceEnqueueCheckoutScripts,
      custom_css: settings.advanced.customCss,
    },
  };
}

export function settingsResponseFromWire(wire: SettingsResponseWire): SettingsResponse {
  return {
    settings: settingsFromWire(wire.settings),
    updatedAt: wire.updated_at,
  };
}

export function integrationFromWire(wire: IntegrationWire): Integration {
  return {
    slug: wire.slug,
    name: wire.name,
    description: wire.description,
    detected: wire.detected,
    version: wire.version,
    active: wire.active,
    iconUrl: wire.icon_url ?? null,
    learnMoreUrl: wire.learn_more_url,
  };
}

export async function getSettings(): Promise<SettingsResponse> {
  return settingsResponseFromWire(settingsResponseWireSchema.parse(await api.get('settings').json()));
}

export async function postSettings(data: Settings): Promise<SettingsResponse> {
  return settingsResponseFromWire(
    settingsResponseWireSchema.parse(await api.post('settings', { json: settingsToWire(data) }).json()),
  );
}

export function markReviewed() {
  return api.post('mark-reviewed').json<boolean>();
}

export function markSetupDone() {
  return api.post('mark-setup-done').json<boolean>();
}

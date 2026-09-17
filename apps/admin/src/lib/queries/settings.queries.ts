import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getSettings, integrationFromWire, postSettings, settingsResponseFromWire } from '@/lib/api/settings.api';
import { logger } from '@/lib/logger';
import {
  integrationWireSchema,
  settingsResponseWireSchema,
  type Integration,
  type Settings,
} from '@/lib/schema/settings.schema';

export const SETTINGS_QUERIES = {
  main: queryOptions({
    queryKey: ['settings', 'main'],
    queryFn: getSettings,
    // The localize bootstrap carries the same envelope as GET /settings.
    initialData: () => settingsResponseFromWire(settingsResponseWireSchema.parse(window.yayCheckoutAdmin.settings)),
    staleTime: Infinity,
    // A GC'd query would reseed from the page-load bootstrap and resurrect pre-save values.
    gcTime: Infinity,
  }),
};

export function useSettingsQuery() {
  return useQuery(SETTINGS_QUERIES.main);
}

export function useSaveSettingsMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationKey: ['settings', 'save'],
    mutationFn: (data: Settings) => postSettings(data),
    // The page toasts the failure itself so it can keep the form dirty.
    meta: { skipGlobalError: true },
    onSuccess: (response) => {
      queryClient.setQueryData(SETTINGS_QUERIES.main.queryKey, response);
    },
  });
}

/** Bootstrap-only (cannot change without a reload). Validated per entry: a bad third-party one is skipped, not fatal. */
export function useIntegrations(): Integration[] {
  const raw = window.yayCheckoutAdmin.integrations;
  if (!Array.isArray(raw)) return [];

  return raw.flatMap((entry) => {
    const parsed = integrationWireSchema.safeParse(entry);
    if (!parsed.success) {
      logger.warn('ignoring malformed integration entry', entry, parsed.error.issues);
      return [];
    }
    return [integrationFromWire(parsed.data)];
  });
}

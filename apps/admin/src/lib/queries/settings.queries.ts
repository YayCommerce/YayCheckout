import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getSettings, integrationFromWire, postSettings, settingsResponseFromWire } from '@/lib/api/settings.api';
import { logger } from '@/lib/logger';
import {
  integrationWireSchema,
  settingsResponseWireSchema,
  type Integration,
  type Settings,
} from '@/lib/schema/settings.schema';

/** ─── Query Options ─────────────────────────────────── */

export const SETTINGS_QUERIES = {
  main: queryOptions({
    queryKey: ['settings', 'main'],
    queryFn: getSettings,
    // The localize bootstrap carries the same envelope GET /settings returns,
    // so the first render never waits on a request. Parsed strictly: a PHP
    // key rename fails here rather than surfacing as undefined in a form.
    initialData: () => settingsResponseFromWire(settingsResponseWireSchema.parse(window.yayCheckoutAdmin.settings)),
    staleTime: Infinity,
    // Never garbage-collect: a recreated query would seed itself from the
    // page-load bootstrap again, resurrecting pre-save values that the next
    // save would then write back over the newer ones.
    gcTime: Infinity,
  }),
};

/** ─── Hooks ─────────────────────────────────────────── */

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

/**
 * Companion plugins detected on this page load. Not a query: detection is a
 * fact about which plugins are active, which cannot change without an admin
 * reload, so the localize bootstrap is the whole source.
 *
 * The list is filterable in PHP, so entries are validated one by one: a
 * malformed third-party entry is logged and skipped rather than taking the
 * settings route down.
 */
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

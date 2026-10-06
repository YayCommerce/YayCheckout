import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  deleteDesignPreset,
  designResponseFromWire,
  getDesign,
  postDesign,
  postDesignPreset,
} from '@/lib/api/design.api';
import { logger } from '@/lib/logger';
import { designResponseWireSchema, type DesignForm, type DesignTokens } from '@/lib/schema/design.schema';

export const DESIGN_QUERIES = {
  main: queryOptions({
    queryKey: ['design', 'main'],
    queryFn: getDesign,
    // The localize bootstrap carries the same envelope as GET /design. A rejected one is
    // left to queryFn, so its error shows where the design is used, not as a render throw.
    initialData: () => {
      const parsed = designResponseWireSchema.safeParse(window.yayCheckoutAdmin.design);
      if (parsed.success) return designResponseFromWire(parsed.data);
      logger.warn('ignoring malformed design bootstrap', parsed.error.issues);
      return undefined;
    },
    staleTime: Infinity,
    // A GC'd query would reseed from the page-load bootstrap and resurrect pre-save values.
    gcTime: Infinity,
  }),
};

export function useDesignQuery() {
  return useQuery(DESIGN_QUERIES.main);
}

export function useSaveDesignMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationKey: ['design', 'save'],
    mutationFn: ({ form, presetId }: { form: DesignForm; presetId: string | null }) => postDesign(form, presetId),
    // The page toasts the failure itself so it can keep the form dirty.
    meta: { skipGlobalError: true },
    onSuccess: (response) => {
      queryClient.setQueryData(DESIGN_QUERIES.main.queryKey, response);
    },
  });
}

export function useSaveDesignPresetMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationKey: ['design', 'preset', 'save'],
    mutationFn: ({ name, tokens }: { name: string; tokens: DesignTokens }) => postDesignPreset(name, tokens),
    // The modal shows the failure inline.
    meta: { skipGlobalError: true },
    onSuccess: (response) => {
      queryClient.setQueryData(DESIGN_QUERIES.main.queryKey, response);
    },
  });
}

export function useDeleteDesignPresetMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationKey: ['design', 'preset', 'delete'],
    mutationFn: (id: string) => deleteDesignPreset(id),
    onSuccess: (response) => {
      queryClient.setQueryData(DESIGN_QUERIES.main.queryKey, response);
    },
  });
}

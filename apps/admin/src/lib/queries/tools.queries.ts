import { useMutation, useQueryClient } from '@tanstack/react-query';

import { exportLayout, importLayout } from '@/lib/api/tools.api';
import { LAYOUT_KEYS } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES } from '@/lib/schema/layout.schema';

/** Export is a read that ends in a download, so it is a mutation: never cached, one file per click. */
export function useExportLayoutMutation() {
  return useMutation({
    mutationKey: ['tools', 'export'],
    mutationFn: exportLayout,
  });
}

export const IMPORT_LAYOUT_MUTATION_KEY = ['tools', 'import'];

export function useImportLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: IMPORT_LAYOUT_MUTATION_KEY,
    mutationFn: importLayout,
    // The Source step renders the refusal next to the button that caused it.
    meta: { skipGlobalError: true },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

import { useMutation, useQueryClient } from '@tanstack/react-query';

import { exportLayout, importLayout, validateImport } from '@/lib/api/tools.api';
import { LAYOUT_KEYS } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES } from '@/lib/schema/layout.schema';

/** Export is a read that ends in a download, so it is a mutation: never cached, one file per click. */
export function useExportLayoutMutation() {
  return useMutation({
    mutationKey: ['tools', 'export'],
    mutationFn: exportLayout,
  });
}

export function useValidateImportMutation() {
  return useMutation({
    mutationKey: ['tools', 'import', 'validate'],
    mutationFn: validateImport,
    // The Import card renders the refusal in its own result box.
    meta: { skipGlobalError: true },
  });
}

export function useImportLayoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['tools', 'import'],
    mutationFn: importLayout,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

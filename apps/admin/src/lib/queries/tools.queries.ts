import { useMutation } from '@tanstack/react-query';

import { exportLayout } from '@/lib/api/tools.api';

/** Export is a read that ends in a download, so it is a mutation: never cached, one file per click. */
export function useExportLayoutMutation() {
  return useMutation({
    mutationKey: ['tools', 'export'],
    mutationFn: exportLayout,
  });
}

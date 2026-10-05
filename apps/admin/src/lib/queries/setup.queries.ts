import { useMutation, useQueryClient } from '@tanstack/react-query';

import { completeSetup } from '@/lib/api/setup.api';
import { DESIGN_QUERIES } from '@/lib/queries/design.queries';
import { invalidateListedLayouts } from '@/lib/queries/layout.queries';

export function useCompleteSetupMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['setup', 'complete'],
    mutationFn: completeSetup,
    meta: { skipGlobalError: true },
    onSuccess: ({ design }) => {
      queryClient.setQueryData(DESIGN_QUERIES.main.queryKey, design);
      invalidateListedLayouts(queryClient);
    },
  });
}

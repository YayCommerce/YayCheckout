import { useMutation, useQueryClient } from '@tanstack/react-query';

import { completeSetup } from '@/lib/api/setup.api';
import { invalidateListedLayouts } from '@/lib/queries/layout.queries';

export function useCompleteSetupMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['setup', 'complete'],
    mutationFn: completeSetup,
    meta: { skipGlobalError: true },
    onSuccess: () => {
      invalidateListedLayouts(queryClient);
    },
  });
}

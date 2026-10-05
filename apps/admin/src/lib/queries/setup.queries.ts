import { useMutation, useQueryClient } from '@tanstack/react-query';

import { completeSetup } from '@/lib/api/setup.api';
import { DESIGN_QUERIES } from '@/lib/queries/design.queries';
import { LAYOUT_KEYS } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES } from '@/lib/schema/layout.schema';

export function useCompleteSetupMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['setup', 'complete'],
    mutationFn: completeSetup,
    // The wizard renders its own inline error.
    meta: { skipGlobalError: true },
    onSuccess: ({ design }) => {
      queryClient.setQueryData(DESIGN_QUERIES.main.queryKey, design);
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.counts });
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.list({ status: LISTED_LAYOUT_STATUSES }) });
    },
  });
}

import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { logger } from '@/lib/logger';

/** skipGlobalError: the screen reports the failure itself. */
export type QueryMeta = {
  skipGlobalError?: boolean;
};

export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        staleTime: 5 * 60 * 1000,
      },
    },

    queryCache: new QueryCache({
      // Toast only a background refetch failure: a first load renders its own error,
      // but a refetch has nothing to render into and would fail invisibly.
      onError: async (error, query) => {
        logger.error(`query failed: ${JSON.stringify(query.queryKey)}`, error);

        if (query.meta?.skipGlobalError || query.state.data === undefined) {
          return;
        }

        toast.error(await getErrorMsg(error));
      },
    }),

    mutationCache: new MutationCache({
      // Mutations always toast: a silent failure looks like nothing happened.
      onError: async (error, variables, _context, mutation) => {
        logger.error(`mutation failed: ${JSON.stringify(mutation.options.mutationKey)}`, error, variables);

        if (mutation.meta?.skipGlobalError) {
          return;
        }

        toast.error(await getErrorMsg(error));
      },
    }),
  });
}

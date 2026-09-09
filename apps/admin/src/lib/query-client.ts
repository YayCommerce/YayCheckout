import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { getErrorMsg } from '@/lib/helpers/response.helper';

/**
 * Opt out of the global error toast for a single query or mutation, when a
 * screen reports the failure itself and a toast would say it twice.
 */
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
      /*
       * Only report a refetch that failed while data is already on screen.
       * A first load has no data to show, so the screen renders the error
       * itself; a background refetch has nothing to render into and would
       * otherwise fail invisibly, leaving stale data looking current.
       */
      onError: async (error, query) => {
        if (query.meta?.skipGlobalError || query.state.data === undefined) {
          return;
        }

        toast.error(await getErrorMsg(error));
      },
    }),

    mutationCache: new MutationCache({
      /*
       * Mutations always report. Unlike a query there is no rendered result to
       * carry the message, so a silent failure looks like nothing happened.
       */
      onError: async (error, _variables, _context, mutation) => {
        if (mutation.meta?.skipGlobalError) {
          return;
        }

        toast.error(await getErrorMsg(error));
      },
    }),
  });
}

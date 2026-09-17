import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

// Own module so a dev-only dynamic import keeps it out of the single production chunk.
/** Own module so main.tsx can reach it through a dev-only dynamic import. */
export default function QueryDevtools() {
  return <ReactQueryDevtools initialIsOpen={false} buttonPosition="bottom-right" />;
}

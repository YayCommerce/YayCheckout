import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

/** Own module, so main.tsx's dev-only dynamic import keeps it out of the single production chunk. */
export default function QueryDevtools() {
  return <ReactQueryDevtools initialIsOpen={false} buttonPosition="bottom-right" />;
}

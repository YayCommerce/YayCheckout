import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

/**
 * Separate module so main.tsx can reach it through a dev-only dynamic import.
 * In a production build the import is behind a statically false branch, so the
 * devtools never enter the bundle — which matters because RegisterProd registers
 * a single js/main.js (Admin\Settings enqueues it) and nothing would load an
 * extra chunk.
 */
export default function QueryDevtools() {
  return <ReactQueryDevtools initialIsOpen={false} buttonPosition="bottom-right" />;
}

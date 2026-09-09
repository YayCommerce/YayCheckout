import './main.css';

import React, { lazy, Suspense } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { domAnimation, LazyMotion } from 'motion/react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';

import { createQueryClient } from '@/lib/query-client';
import { getManagerRouter } from '@/router';

const queryClient = createQueryClient();

/*
 * import.meta.env.DEV is replaced with a literal at build time, so the whole
 * branch and its dynamic import are dropped from the production bundle. That
 * keeps the build to the single js/main.js that RegisterProd enqueues.
 */
const QueryDevtools = import.meta.env.DEV ? lazy(() => import('@/components/QueryDevtools')) : null;

createRoot(document.getElementById('yaycheckout') as HTMLElement).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <LazyMotion features={domAnimation}>
        <RouterProvider router={getManagerRouter()} />
      </LazyMotion>
      {QueryDevtools && (
        <Suspense fallback={null}>
          <QueryDevtools />
        </Suspense>
      )}
    </QueryClientProvider>
  </React.StrictMode>,
);

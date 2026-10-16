import './main.css';

import React, { lazy, Suspense } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';

import { createQueryClient } from '@/lib/query-client';
import { getManagerRouter } from '@/router';

const queryClient = createQueryClient();

// Folded out of the production bundle, which must stay a single chunk.
const QueryDevtools = import.meta.env.DEV ? lazy(() => import('@/components/shared/QueryDevtools')) : null;

createRoot(document.getElementById('yaycheckout') as HTMLElement).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={getManagerRouter()} />
      {QueryDevtools && (
        <Suspense fallback={null}>
          <QueryDevtools />
        </Suspense>
      )}
    </QueryClientProvider>
  </React.StrictMode>,
);

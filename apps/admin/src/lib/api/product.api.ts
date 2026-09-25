import { z } from 'zod';

import { createApi } from '@/lib/api/api';

/** Woo's own REST routes: product search and paging without a route of ours. */
const wcApi = createApi('wc/v3');

const productOptionSchema = z.object({ id: z.number(), name: z.string() });

const PRODUCT_PAGE_SIZE = 10;

/** One page of published products, id and name only. */
export async function getProducts({ search, page }: { search: string; page: number }) {
  const response = await wcApi.get('products', {
    searchParams: {
      search,
      page: String(page),
      per_page: String(PRODUCT_PAGE_SIZE),
      status: 'publish',
      _fields: 'id,name',
    },
  });

  return {
    items: z.array(productOptionSchema).parse(await response.json()),
    page,
    totalPages: Number(response.headers.get('X-WP-TotalPages') ?? 0),
  };
}

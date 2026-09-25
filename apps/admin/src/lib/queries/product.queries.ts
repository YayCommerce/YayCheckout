import { keepPreviousData, useInfiniteQuery } from '@tanstack/react-query';

import { getProducts } from '@/lib/api/product.api';

export function useInfiniteProductsQuery(search: string, enabled = true) {
  return useInfiniteQuery({
    queryKey: ['products', 'infinite', search],
    queryFn: ({ pageParam }) => getProducts({ search, page: pageParam }),
    enabled,
    initialPageParam: 1,
    getNextPageParam: (last) => (last.page < last.totalPages ? last.page + 1 : undefined),
    // A new search is a new key; without this the open list empties while it loads.
    placeholderData: keepPreviousData,
  });
}

import { QueryMeta } from '@/lib/query-client';
import { AdminData, Meta } from './localize.type';

declare global {
  interface Window {
    yayCheckoutMeta: Meta;
    yayCheckoutAdmin: AdminData;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    wp: any;
  }
}

declare module '@tanstack/react-query' {
  interface Register {
    queryMeta: QueryMeta;
    mutationMeta: QueryMeta;
  }
}

declare module '@tanstack/react-table' {
  interface ColumnMeta<TData, TValue> {
    align?: 'left' | 'center' | 'right';
    isCheckbox?: boolean;
  }
}

declare module 'rooks' {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  type AnyFunction = (...args: any[]) => any;
  type DebounceOptions = {
    leading?: boolean;
    trailing?: boolean;
    maxWait?: number;
  };

  declare function useDebounceFn<F extends AnyFunction>(
    func: F,
    delay: number,
    options?: DebounceOptions,
  ): [(...args: Parameters<F>) => void, boolean];
}

export {};

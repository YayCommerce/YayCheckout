import { useRef, useState } from 'react';
import { useDebounceFn } from 'rooks';

/**
 * A search box's text (`input`) and the term a query uses (`search`), applied 300 ms after typing
 * stops. `onApply` runs in the same update as the new term, e.g. a page reset: a separate effect
 * would first fetch a page that may not exist under the new filter.
 */
export function useDebouncedSearch(onApply?: () => void) {
  const [input, setInputState] = useState('');
  const [search, setSearch] = useState('');
  // useDebounceFn has no cancel; the trailing callback checks this ref to drop a stale value.
  const inputRef = useRef('');

  const [apply] = useDebounceFn((value: string) => {
    if (value !== inputRef.current) return;

    setSearch(value);
    onApply?.();
  }, 300);

  function setInput(value: string) {
    inputRef.current = value;
    setInputState(value);
    apply(value);
  }

  /** Clears the box and the term at once, with no debounce. */
  function reset() {
    inputRef.current = '';
    setInputState('');
    setSearch('');
  }

  return { input, search, setInput, reset };
}

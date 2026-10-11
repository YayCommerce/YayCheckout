import { useEffect, useState } from 'react';

/**
 * A typed phrase that gates an action that cannot be undone. Cleared whenever `resetKey` changes,
 * so reopening the dialog for another row starts empty.
 */
export function useTypedConfirm(phrase: string, resetKey: unknown, isPending: boolean) {
  const [typed, setTyped] = useState('');

  useEffect(() => setTyped(''), [resetKey]);

  return { typed, setTyped, confirmed: typed.trim() === phrase && !isPending };
}

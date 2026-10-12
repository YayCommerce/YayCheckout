import { useRef } from 'react';
import { toast } from 'sonner';

import { copyToClipboard } from '@/lib/helpers/clipboard.helper';

/**
 * Copies text and toasts the result. Put `fieldRef` on the field that shows the text: without a
 * clipboard API (http origins) it is selected instead, and the toast asks for Ctrl+C.
 */
export function useCopyWithFallback<T extends HTMLInputElement | HTMLTextAreaElement>(
  copiedMessage: string,
  pressToCopyMessage: string,
) {
  const fieldRef = useRef<T>(null);

  async function copy(text: string) {
    if (await copyToClipboard(text, fieldRef.current)) {
      toast.success(copiedMessage);
    } else {
      toast.info(pressToCopyMessage);
    }
  }

  return { fieldRef, copy };
}

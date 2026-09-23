/**
 * Writes text to the clipboard; on http origins there is no clipboard API,
 * so the fallback selects the given field and returns false so the caller
 * can tell the user to press Ctrl+C.
 */
export async function copyToClipboard(
  text: string,
  fallbackField: HTMLInputElement | HTMLTextAreaElement | null,
): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    fallbackField?.select();
    return false;
  }
}

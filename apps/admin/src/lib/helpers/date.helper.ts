import { dateI18n, getSettings } from '@wordpress/date';

// Site date/time format, viewer's timezone — a deliberate departure from
// core, which shows the store's timezone to everyone.

const viewerTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

/** Null and empty pass through as '' so callers can `|| __('never')`. */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '';
  return dateI18n(getSettings().formats.datetime, iso, viewerTimeZone);
}

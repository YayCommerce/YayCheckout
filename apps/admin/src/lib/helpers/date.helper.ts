import { dateI18n, getSettings, humanTimeDiff } from '@wordpress/date';

// Site date/time format, viewer's timezone — a deliberate departure from
// core, which shows the store's timezone to everyone.

const viewerTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

/**
 * Formats an ISO 8601 timestamp in the site's date+time format, in the
 * browser's zone. Null and empty pass through as '' so callers can `|| __('never')`.
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '';
  return dateI18n(getSettings().formats.datetime, iso, viewerTimeZone);
}

/** "in 59 minutes", "in 2 hours" — an ISO 8601 timestamp relative to now. */
export function formatRelative(iso: string): string {
  return humanTimeDiff(iso);
}

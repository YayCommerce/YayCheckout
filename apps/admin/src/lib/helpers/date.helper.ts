import { dateI18n, getSettings, humanTimeDiff } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

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

/**
 * "14/10/2026 at 14:01": a numeric day-first date and a 24-hour time, in the browser's zone.
 * Null and empty pass through as ''.
 */
export function formatDateAtTime(iso: string | null | undefined): string {
  if (!iso) return '';
  return sprintf(
    /* translators: 1: date such as 14/10/2026, 2: time such as 14:01 */
    __('%1$s at %2$s', 'yaycheckout'),
    dateI18n('d/m/Y', iso, viewerTimeZone),
    dateI18n('H:i', iso, viewerTimeZone),
  );
}

/** "in 59 minutes", "in 2 hours" — an ISO 8601 timestamp relative to now. */
export function formatRelative(iso: string): string {
  return humanTimeDiff(iso);
}

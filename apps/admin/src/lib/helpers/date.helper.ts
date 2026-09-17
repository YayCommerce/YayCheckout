import { dateI18n, getSettings } from '@wordpress/date';

/*
 * Timestamps for display. The counterpart of PHP's Helpers\DateHelper: PHP
 * sends every timestamp as ISO 8601 with an explicit UTC offset, and this is
 * the one place the SPA turns one into text.
 *
 * Output follows the site's Settings → General date and time formats
 * (core hands them to `wp.date` on every admin page), so what the plugin
 * shows matches what the Posts and Orders lists show. The zone, though, is
 * the viewer's own — a deliberate departure from core, which renders the
 * store's timezone for everyone: a store manager reads "published 4:19 pm"
 * against the clock on their wall, not against the store's configured zone.
 */

/** The browser's IANA zone, e.g. "Asia/Saigon". */
const viewerTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

/**
 * Format an ISO 8601 timestamp in the site's date+time format, in the
 * viewer's local zone. Null and empty pass through as '' so callers can
 * `|| __('never')` without a null check of their own.
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '';
  return dateI18n(getSettings().formats.datetime, iso, viewerTimeZone);
}

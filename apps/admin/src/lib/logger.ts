/**
 * Namespaced console logging for the admin app.
 *
 * The WordPress admin console is a busy place, so every line carries a prefix
 * you can filter on. The counterpart on the server is Helpers\Logger, which
 * writes to WooCommerce's log; this one only reaches the browser, so treat it
 * as a debugging aid rather than a record of anything.
 *
 * Errors and warnings are kept in production deliberately: when a merchant
 * reports a problem, "open the console and screenshot it" is often the fastest
 * route to a cause. Info and debug are development-only so a working install
 * stays quiet.
 */
const PREFIX = '[YayCheckout]';

export const logger = {
  /** Something failed that nobody asked for. Kept in production builds. */
  error(message: string, ...detail: unknown[]): void {
    console.error(PREFIX, message, ...detail);
  },

  /** Something recoverable, or an expected failure worth noticing. */
  warn(message: string, ...detail: unknown[]): void {
    console.warn(PREFIX, message, ...detail);
  },

  info(message: string, ...detail: unknown[]): void {
    if (import.meta.env.DEV) {
      console.info(PREFIX, message, ...detail);
    }
  },

  debug(message: string, ...detail: unknown[]): void {
    if (import.meta.env.DEV) {
      console.debug(PREFIX, message, ...detail);
    }
  },
};

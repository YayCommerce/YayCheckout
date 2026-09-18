/**
 * Prefixed console logging. error/warn stay in production so a merchant can
 * screenshot the console; info/debug are development-only.
 */
const PREFIX = '[YayCheckout]';

export const logger = {
  error(message: string, ...detail: unknown[]): void {
    console.error(PREFIX, message, ...detail);
  },

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

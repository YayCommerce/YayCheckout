/**
 * Build-time edition. `__YAYCHECKOUT_PLAN__` is a compile-time constant, so
 * `{isPro && …}` branches fold out of the Free bundle. Plan, not license.
 */
export const isPro = __YAYCHECKOUT_PLAN__ === 'pro';

export const isFree = !isPro;

/** Same value as `isPro`, for components that read better with a hook. */
export function useIsPro(): boolean {
  return isPro;
}

/**
 * Build-time edition. `__YAYCHECKOUT_PLAN__` is a compile-time constant, so
 * `{isPro && …}` branches fold out of the Free bundle. Plan, not license.
 */
export type Plan = 'free' | 'pro';

export const isPro = __YAYCHECKOUT_PLAN__ === 'pro';

export const isFree = !isPro;

export function useIsPro(): boolean {
  return isPro;
}

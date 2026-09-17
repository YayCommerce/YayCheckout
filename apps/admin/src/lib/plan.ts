/**
 * Which edition of the plugin this bundle was built for.
 *
 * The single source in the SPA. `__YAYCHECKOUT_PLAN__` is a compile-time
 * constant injected by vite.config.ts, so the bundler can fold `isPro` to a
 * literal and drop `{isPro && …}` call-site branches from the Free build.
 * `ProOnly` itself only gates rendering — see ProGate.tsx.
 *
 * This is *plan*, not license: it says which edition was built, not whether a
 * license is currently valid. An expired Pro license is still the Pro plan.
 * License state is runtime and would come from the server, not from here.
 */
export type Plan = 'free' | 'pro';

export const isPro = __YAYCHECKOUT_PLAN__ === 'pro';

export const isFree = !isPro;

/** Same value as `isPro`, for components that read better with a hook. */
export function useIsPro(): boolean {
  return isPro;
}

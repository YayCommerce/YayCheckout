/// <reference types="vite/client" />

/**
 * Build-time Free/Pro plan, injected as a constant by vite.config.ts.
 *
 * Not a VITE_ env var: the release script sets the plan per invocation, and a
 * committed env file would be a second source able to disagree with it.
 *
 * This is *plan*, not license — it says which edition was built, not whether a
 * license is currently valid. Read it through lib/plan.ts, never directly.
 */
declare const __YAYCHECKOUT_PLAN__: 'free' | 'pro';

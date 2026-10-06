/// <reference types="vite/client" />

/**
 * Which edition was built, not whether a license is valid. vite.config.ts injects it; read it through lib/plan.ts.
 * Not a VITE_ env var: the release script sets the plan per run, and a committed env file could disagree with it.
 */
declare const __YAYCHECKOUT_PLAN__: 'free' | 'pro';

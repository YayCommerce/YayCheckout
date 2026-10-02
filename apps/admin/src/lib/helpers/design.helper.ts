import type { DesignPreset, DesignTokens, TokenSection } from '@/lib/schema/design.schema';

/**
 * Number of token leaves in `a` that differ from `b`, within one section or across all of them.
 * A section switched off on both sides counts as equal: its values print nothing on the storefront.
 */
export function countTokenDiffs(a: DesignTokens, b: DesignTokens, section?: TokenSection): number {
  const sections = section ? [section] : (Object.keys(a) as TokenSection[]);

  return sections.reduce((total, current) => {
    const [x, y] = [a[current], b[current]] as Record<string, unknown>[];
    if (!x.enabled && !y.enabled) {
      return total;
    }
    return total + Object.keys(x).filter((key) => x[key] !== y[key]).length;
  }, 0);
}

/** Whether a Design page group is on; Custom CSS has no switch, so it always is. */
export function isGroupOn(tokens: DesignTokens, id: TokenSection | 'customCss'): boolean {
  return 'customCss' === id || tokens[id].enabled;
}

/** A preset counts as applied only while the tokens still match it; a hand edit silently leaves it. */
export function activePresetIdFor(
  presets: DesignPreset[],
  presetId: string | null,
  tokens: DesignTokens,
): string | null {
  const preset = presets.find((preset) => preset.id === presetId && 0 === countTokenDiffs(preset.tokens, tokens));
  return preset?.id ?? null;
}

import type { DesignPreset, DesignTokens, TokenSection } from '@/lib/schema/design.schema';

/** Number of token leaves in `a` that differ from `b`, within one section or across all of them. */
export function countTokenDiffs(a: DesignTokens, b: DesignTokens, section?: TokenSection): number {
  const sections = section ? [section] : (Object.keys(a) as TokenSection[]);

  return sections.reduce((total, current) => {
    const [x, y] = [a[current], b[current]] as Record<string, unknown>[];
    return total + Object.keys(x).filter((key) => x[key] !== y[key]).length;
  }, 0);
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

import { __ } from '@wordpress/i18n';

import { FONT_FAMILIES, FONT_FAMILY_KEYS, type FontFamily } from '@/lib/schema/design.schema';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

// Radix Select treats value="" as "nothing selected", so the theme-default option needs a stand-in.
const THEME_DEFAULT_FONT = '__theme_default__';

/** The typography font picker: every bundled font plus "Theme default" (stored as ''). */
export function FontFamilySelect({
  value,
  onChange,
  disabled,
  size,
}: {
  value: FontFamily;
  onChange: (font: FontFamily) => void;
  disabled?: boolean;
  size?: 'sm' | 'default';
}) {
  return (
    <Select
      value={value || THEME_DEFAULT_FONT}
      onValueChange={(next) => onChange(next === THEME_DEFAULT_FONT ? '' : (next as FontFamily))}
      disabled={disabled}
    >
      <SelectTrigger size={size} className="w-full">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {FONT_FAMILY_KEYS.map((font) => (
          <SelectItem key={font} value={font || THEME_DEFAULT_FONT}>
            {font ? FONT_FAMILIES[font] : __('Theme default', 'yaycheckout')}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

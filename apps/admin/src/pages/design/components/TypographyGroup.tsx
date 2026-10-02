import { __ } from '@wordpress/i18n';

import { BASE_SIZE_RANGE, FONT_FAMILIES, FONT_FAMILY_KEYS } from '@/lib/schema/design.schema';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PxInput } from './PxInput';
import { TokenField } from './TokenField';

// Radix Select treats value="" as "nothing selected", so the theme-default option needs a stand-in.
const THEME_DEFAULT_FONT = '__theme_default__';

export function TypographyGroup() {
  return (
    <>
      <TokenField section="typography" token="fontFamily" label={__('Font family', 'yaycheckout')}>
        {(field) => (
          <Select
            value={field.value || THEME_DEFAULT_FONT}
            onValueChange={(value) => field.onChange(value === THEME_DEFAULT_FONT ? '' : value)}
          >
            <SelectTrigger size="sm" className="w-full">
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
        )}
      </TokenField>

      <TokenField section="typography" token="baseSize" label={__('Base size', 'yaycheckout')}>
        {(field) => <PxInput value={field.value} onChange={field.onChange} range={BASE_SIZE_RANGE} />}
      </TokenField>
    </>
  );
}

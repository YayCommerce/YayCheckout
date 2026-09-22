import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { BASE_SIZE_RANGE, FONT_FAMILIES, FONT_FAMILY_KEYS, type DesignForm } from '@/lib/schema/design.schema';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { PxInput } from './PxInput';
import { TokenField } from './TokenField';

// Radix Select treats value="" as "nothing selected", so the theme-default option needs a stand-in.
const THEME_DEFAULT_FONT = '__theme_default__';

export function TypographyGroup() {
  const { watch } = useFormContext<DesignForm>();
  const inherit = watch('tokens.typography.inherit');

  return (
    <>
      <TokenField section="typography" token="inherit" label={__('Inherit theme', 'yaycheckout')}>
        {(field) => <Switch checked={field.value} onCheckedChange={field.onChange} />}
      </TokenField>

      <TokenField section="typography" token="fontFamily" label={__('Font family', 'yaycheckout')}>
        {(field) => (
          <Select
            value={field.value || THEME_DEFAULT_FONT}
            onValueChange={(value) => field.onChange(value === THEME_DEFAULT_FONT ? '' : value)}
            disabled={inherit}
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
        {(field) => (
          <PxInput value={field.value} onChange={field.onChange} range={BASE_SIZE_RANGE} disabled={inherit} />
        )}
      </TokenField>
    </>
  );
}

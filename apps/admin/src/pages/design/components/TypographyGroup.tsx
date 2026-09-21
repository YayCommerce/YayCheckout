import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { FONT_FAMILIES, FONT_FAMILY_KEYS, type DesignForm } from '@/lib/schema/design.schema';
import { NumberInputInput, NumberInputRoot, NumberInputUnit } from '@/components/ui/number-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TokenField } from './TokenField';

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
          <Select value={field.value} onValueChange={(value) => field.onChange(value)} disabled={inherit}>
            <SelectTrigger size="sm" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {FONT_FAMILY_KEYS.map((font) => (
                <SelectItem key={font} value={font}>
                  {font ? FONT_FAMILIES[font] : __('Theme default', 'yaycheckout')}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </TokenField>

      <TokenField section="typography" token="baseSize" label={__('Base size', 'yaycheckout')}>
        {(field) => (
          <NumberInputRoot
            value={field.value}
            onValueChange={field.onChange}
            min={10}
            max={24}
            size="small"
            disabled={inherit}
            className="w-28"
          >
            <NumberInputInput />
            <NumberInputUnit unit="px" />
          </NumberInputRoot>
        )}
      </TokenField>
    </>
  );
}

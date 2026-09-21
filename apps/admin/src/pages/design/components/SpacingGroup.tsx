import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { SPACING_SCALE_OPTIONS, type DesignForm } from '@/lib/schema/design.schema';
import { NumberInputInput, NumberInputRoot, NumberInputUnit } from '@/components/ui/number-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TokenField } from './TokenField';

/**
 * Radius here is the global corner radius: changing it also pushes into
 * buttons.radius and inputs.radius, since those default to following it.
 */
export function SpacingGroup() {
  const { setValue } = useFormContext<DesignForm>();

  return (
    <>
      <TokenField section="spacing" token="scale" label={__('Density', 'yaycheckout')}>
        {(field) => (
          <Select value={String(field.value)} onValueChange={(value) => field.onChange(Number(value))}>
            <SelectTrigger size="sm" className="w-24">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {SPACING_SCALE_OPTIONS.map((scale) => (
                <SelectItem key={scale} value={String(scale)}>
                  {scale}×
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </TokenField>

      <TokenField section="spacing" token="radius" label={__('Radius', 'yaycheckout')}>
        {(field) => (
          <NumberInputRoot
            value={field.value}
            onValueChange={(value) => {
              field.onChange(value);
              setValue('tokens.buttons.radius', value, { shouldDirty: true });
              setValue('tokens.inputs.radius', value, { shouldDirty: true });
            }}
            min={0}
            max={24}
            size="small"
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

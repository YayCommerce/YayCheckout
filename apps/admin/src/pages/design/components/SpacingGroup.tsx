import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { RADIUS_RANGE, SPACING_SCALE_OPTIONS, type DesignForm } from '@/lib/schema/design.schema';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PxInput } from './PxInput';
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
          <PxInput
            value={field.value}
            onChange={(value) => {
              field.onChange(value);
              setValue('tokens.buttons.radius', value, { shouldDirty: true });
              setValue('tokens.inputs.radius', value, { shouldDirty: true });
            }}
            range={RADIUS_RANGE}
          />
        )}
      </TokenField>
    </>
  );
}

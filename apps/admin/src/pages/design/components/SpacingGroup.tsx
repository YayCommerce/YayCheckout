import { __ } from '@wordpress/i18n';

import { SPACING_SCALE_OPTIONS } from '@/lib/schema/design.schema';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TokenField } from './TokenField';

export function SpacingGroup() {
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
    </>
  );
}

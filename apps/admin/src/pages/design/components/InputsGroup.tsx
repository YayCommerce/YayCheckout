import { __ } from '@wordpress/i18n';

import { RADIUS_RANGE, type DesignTokens } from '@/lib/schema/design.schema';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { PxInput } from './PxInput';
import { TokenField } from './TokenField';

export const INPUT_STYLE_LABELS: Record<DesignTokens['inputs']['style'], string> = {
  boxed: __('Boxed', 'yaycheckout'),
  underline: __('Underline', 'yaycheckout'),
};

export const INPUT_SIZE_LABELS: Record<DesignTokens['inputs']['size'], string> = {
  small: __('Small', 'yaycheckout'),
  medium: __('Medium', 'yaycheckout'),
  large: __('Large', 'yaycheckout'),
};

export function InputsGroup() {
  return (
    <>
      <TokenField section="inputs" token="style" label={__('Style', 'yaycheckout')}>
        {(field) => (
          <Segmented size="sm" value={field.value} onValueChange={(value) => value && field.onChange(value)}>
            {Object.entries(INPUT_STYLE_LABELS).map(([value, label]) => (
              <SegmentedItem key={value} value={value}>
                {label}
              </SegmentedItem>
            ))}
          </Segmented>
        )}
      </TokenField>

      <TokenField section="inputs" token="size" label={__('Size', 'yaycheckout')}>
        {(field) => (
          <Segmented size="sm" value={field.value} onValueChange={(value) => value && field.onChange(value)}>
            {Object.entries(INPUT_SIZE_LABELS).map(([value, label]) => (
              <SegmentedItem key={value} value={value} className="px-2">
                {label}
              </SegmentedItem>
            ))}
          </Segmented>
        )}
      </TokenField>

      <TokenField section="inputs" token="radius" label={__('Radius', 'yaycheckout')}>
        {(field) => <PxInput value={field.value} onChange={field.onChange} range={RADIUS_RANGE} />}
      </TokenField>
    </>
  );
}

import { __ } from '@wordpress/i18n';

import { RADIUS_RANGE, type DesignTokens } from '@/lib/schema/design.schema';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { PxInput } from './PxInput';
import { TokenField } from './TokenField';

export const BUTTON_STYLE_LABELS: Record<DesignTokens['buttons']['style'], string> = {
  fill: __('Fill', 'yaycheckout'),
  outline: __('Outline', 'yaycheckout'),
};

export function ButtonsGroup() {
  return (
    <>
      <TokenField section="buttons" token="style" label={__('Style', 'yaycheckout')}>
        {(field) => (
          <Segmented size="sm" value={field.value} onValueChange={(value) => value && field.onChange(value)}>
            {Object.entries(BUTTON_STYLE_LABELS).map(([value, label]) => (
              <SegmentedItem key={value} value={value}>
                {label}
              </SegmentedItem>
            ))}
          </Segmented>
        )}
      </TokenField>

      <TokenField section="buttons" token="radius" label={__('Radius', 'yaycheckout')}>
        {(field) => <PxInput value={field.value} onChange={field.onChange} range={RADIUS_RANGE} />}
      </TokenField>
    </>
  );
}

import { __ } from '@wordpress/i18n';

import { NumberInputInput, NumberInputRoot, NumberInputUnit } from '@/components/ui/number-input';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { TokenField } from './TokenField';

export function ButtonsGroup() {
  return (
    <>
      <TokenField section="buttons" token="style" label={__('Style', 'yaycheckout')}>
        {(field) => (
          <Segmented size="sm" value={field.value} onValueChange={(value) => value && field.onChange(value)}>
            <SegmentedItem value="fill">{__('Fill', 'yaycheckout')}</SegmentedItem>
            <SegmentedItem value="outline">{__('Outline', 'yaycheckout')}</SegmentedItem>
          </Segmented>
        )}
      </TokenField>

      <TokenField section="buttons" token="radius" label={__('Radius', 'yaycheckout')}>
        {(field) => (
          <NumberInputRoot
            value={field.value}
            onValueChange={field.onChange}
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

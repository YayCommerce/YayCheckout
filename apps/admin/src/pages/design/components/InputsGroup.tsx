import { __ } from '@wordpress/i18n';

import { NumberInputInput, NumberInputRoot, NumberInputUnit } from '@/components/ui/number-input';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { TokenField } from './TokenField';

export function InputsGroup() {
  return (
    <>
      <TokenField section="inputs" token="style" label={__('Style', 'yaycheckout')}>
        {(field) => (
          <Segmented size="sm" value={field.value} onValueChange={(value) => value && field.onChange(value)}>
            <SegmentedItem value="boxed">{__('Boxed', 'yaycheckout')}</SegmentedItem>
            <SegmentedItem value="underline">{__('Underline', 'yaycheckout')}</SegmentedItem>
          </Segmented>
        )}
      </TokenField>

      <TokenField section="inputs" token="size" label={__('Size', 'yaycheckout')}>
        {(field) => (
          <Segmented size="sm" value={field.value} onValueChange={(value) => value && field.onChange(value)}>
            <SegmentedItem value="small" className="px-2">
              {__('Small', 'yaycheckout')}
            </SegmentedItem>
            <SegmentedItem value="medium" className="px-2">
              {__('Medium', 'yaycheckout')}
            </SegmentedItem>
            <SegmentedItem value="large" className="px-2">
              {__('Large', 'yaycheckout')}
            </SegmentedItem>
          </Segmented>
        )}
      </TokenField>

      <TokenField section="inputs" token="radius" label={__('Radius', 'yaycheckout')}>
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

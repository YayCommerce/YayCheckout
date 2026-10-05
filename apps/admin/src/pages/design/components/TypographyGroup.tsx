import { __ } from '@wordpress/i18n';

import { BASE_SIZE_RANGE } from '@/lib/schema/design.schema';
import { FontFamilySelect } from '@/components/shared/FontFamilySelect';
import { PxInput } from './PxInput';
import { TokenField } from './TokenField';

export function TypographyGroup() {
  return (
    <>
      <TokenField section="typography" token="fontFamily" label={__('Font family', 'yaycheckout')}>
        {(field) => <FontFamilySelect value={field.value} onChange={field.onChange} size="sm" />}
      </TokenField>

      <TokenField section="typography" token="baseSize" label={__('Base size', 'yaycheckout')}>
        {(field) => <PxInput value={field.value} onChange={field.onChange} range={BASE_SIZE_RANGE} />}
      </TokenField>
    </>
  );
}

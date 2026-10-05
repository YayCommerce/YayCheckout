import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { BASE_SIZE_RANGE, type DesignForm } from '@/lib/schema/design.schema';
import { Switch } from '@/components/ui/switch';
import { FontFamilySelect } from '@/components/shared/FontFamilySelect';
import { PxInput } from './PxInput';
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
        {(field) => <FontFamilySelect value={field.value} onChange={field.onChange} disabled={inherit} size="sm" />}
      </TokenField>

      <TokenField section="typography" token="baseSize" label={__('Base size', 'yaycheckout')}>
        {(field) => (
          <PxInput value={field.value} onChange={field.onChange} range={BASE_SIZE_RANGE} disabled={inherit} />
        )}
      </TokenField>
    </>
  );
}

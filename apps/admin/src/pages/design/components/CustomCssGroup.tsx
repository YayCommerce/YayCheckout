import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { CUSTOM_CSS_MAX_BYTES, type DesignForm } from '@/lib/schema/design.schema';
import { FormField } from '@/components/ui/form';
import { Textarea } from '@/components/ui/textarea';

export function CustomCssGroup() {
  const { control } = useFormContext<DesignForm>();

  return (
    <div className="p-4">
      <FormField
        control={control}
        name="customCss"
        render={({ field }) => (
          <Textarea
            {...field}
            rows={10}
            maxLength={CUSTOM_CSS_MAX_BYTES}
            placeholder={__('.yaycheckout-checkout { }', 'yaycheckout')}
            className="font-mono text-xs"
          />
        )}
      />
    </div>
  );
}

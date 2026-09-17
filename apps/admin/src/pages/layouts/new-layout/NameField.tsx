import { __ } from '@wordpress/i18n';

import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

/** No uniqueness check: titles may repeat, as Pages do. */
export function NameField() {
  return (
    <FormField
      name="name"
      render={({ field }) => (
        <FormItem className="max-w-md">
          <FormLabel>{__('Name', 'yaycheckout')}</FormLabel>
          <FormControl>
            <Input {...field} value={field.value ?? ''} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

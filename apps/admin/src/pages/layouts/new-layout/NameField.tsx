import { __ } from '@wordpress/i18n';

import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

/**
 * The Name field shared by both New-layout steps (yc02-9, yc02-10/10b). Reads
 * its form context from the ambient FormProvider in NewLayoutModal, so no
 * `control` prop is needed here.
 *
 * No uniqueness check: layout titles may repeat, same as WordPress Pages —
 * the post_name slug is what WordPress deduplicates, automatically, once the
 * layout is first published (see LayoutRepository::publish()).
 */
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

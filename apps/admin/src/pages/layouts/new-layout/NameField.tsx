import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

/**
 * The Name field shared by both New-layout steps (yc02-9, yc02-10/10b). Reads
 * its form context from the ambient FormProvider in NewLayoutModal, so no
 * `control` prop is needed here.
 *
 * `suggestedName`/`onUseSuggestedName` render the yc02-16 duplicate-name
 * recovery UI — the server's suggested fix, offered only while that server
 * error is still the field's active error (form.setError('name', ...) in
 * NewLayoutModal).
 */
export function NameField({
  suggestedName,
  onUseSuggestedName,
}: {
  suggestedName: string | null;
  onUseSuggestedName: () => void;
}) {
  return (
    <FormField
      name="name"
      render={({ field, fieldState }) => (
        <FormItem className="max-w-md">
          <FormLabel>{__('Name', 'yaycheckout')}</FormLabel>
          <FormControl>
            <Input {...field} value={field.value ?? ''} />
          </FormControl>
          <FormMessage />
          {suggestedName && fieldState.error && (
            <div className="flex flex-col gap-1.5">
              <p className="text-muted-foreground text-xs">
                {__('Names have to be unique so preview links and the assignment list stay readable.', 'yaycheckout')}
              </p>
              <Button size="sm" variant="outline" className="w-fit" onClick={onUseSuggestedName}>
                {__('Use', 'yaycheckout')} "{suggestedName}"
              </Button>
            </div>
          )}
        </FormItem>
      )}
    />
  );
}

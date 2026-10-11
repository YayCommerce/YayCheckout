import { useFormContext, useWatch } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { layoutNameSchema, type NewLayoutFormValues } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { FormField } from '@/components/ui/form';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { NameField } from '@/pages/layouts/new-layout/NameField';

/** Name and source. Choosing From library moves straight to the library step; Create layout here makes a blank layout. */
export function SourceStep({
  onChooseLibrary,
  onCancel,
  isPending,
  rootError,
}: {
  onChooseLibrary: () => void;
  onCancel: () => void;
  isPending: boolean;
  rootError?: string;
}) {
  const { control } = useFormContext<NewLayoutFormValues>();
  const name = useWatch<NewLayoutFormValues, 'name'>({ control, name: 'name' });

  const disabled = !layoutNameSchema.safeParse(name).success;

  return (
    <div className="flex flex-col gap-5">
      <NameField />

      <FormField
        name="source"
        render={({ field }) => (
          <RadioGroup
            value={field.value}
            onValueChange={(value) => {
              field.onChange(value);
              if (value === 'library') {
                onChooseLibrary();
              }
            }}
            className="grid grid-cols-2 gap-4"
          >
            <label
              className={cn(
                'flex cursor-pointer flex-col gap-2 rounded-md border-2 p-4',
                'has-data-[state=checked]:border-primary',
              )}
            >
              <div className="flex items-center gap-2">
                <RadioGroupItem value="blank" />
                <span className="text-sm font-semibold">{__('Blank', 'yaycheckout')}</span>
              </div>
              <p className="text-muted-foreground text-xs">
                {__('Root + required sections. Always a working checkout.', 'yaycheckout')}
              </p>
            </label>

            <label className="hover:border-muted-foreground flex cursor-pointer flex-col gap-2 rounded-md border p-4">
              <div className="flex items-center gap-2">
                <RadioGroupItem value="library" />
                <span className="text-sm font-semibold">{__('From library', 'yaycheckout')}</span>
              </div>
              <p className="text-muted-foreground text-xs">
                {__('Pick a template, then edit. Opens the library picker.', 'yaycheckout')}
              </p>
            </label>
          </RadioGroup>
        )}
      />

      {rootError && <p className="text-destructive text-sm">{rootError}</p>}

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-xs">
          {__('Creating a layout never changes your live checkout. Set it active when ready.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={onCancel}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button type="submit" disabled={disabled || isPending}>
            {isPending ? __('Creating…', 'yaycheckout') : __('Create layout', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </div>
  );
}

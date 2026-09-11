import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { NameField } from '@/pages/layouts/new-layout/NameField';
import type { FormError } from '@/pages/layouts/new-layout/NewLayoutModal';

/** yc02-9: choosing Blank vs From library. Picking "From library" advances
 * straight to the library step — it never submits from here. */
export function SourceStep({
  name,
  onNameChange,
  onChooseLibrary,
  onCancel,
  onSubmit,
  onUseSuggestedName,
  isPending,
  error,
}: {
  name: string;
  onNameChange: (value: string) => void;
  onChooseLibrary: () => void;
  onCancel: () => void;
  onSubmit: () => void;
  onUseSuggestedName: (suggestedName: string) => void;
  isPending: boolean;
  error: FormError | null;
}) {
  const disabled = '' === name.trim() || error?.kind === 'duplicate';

  return (
    <div className="flex flex-col gap-5">
      <NameField name={name} onNameChange={onNameChange} error={error} onUseSuggestedName={onUseSuggestedName} />

      <RadioGroup
        value="blank"
        onValueChange={(value) => {
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

      {error?.kind === 'generic' && <p className="text-destructive text-sm">{error.message}</p>}

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-xs">
          {__('Creating a layout never changes your live checkout. Set it active when ready.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onCancel}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button onClick={onSubmit} disabled={disabled || isPending}>
            {isPending ? __('Creating…', 'yaycheckout') : __('Create draft', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </div>
  );
}

import { useFormContext, useWatch } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { NewLayoutFormValues } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { TemplatePicker } from '@/components/shared/template-picker/TemplatePicker';
import { NameField } from '@/pages/layouts/new-layout/NameField';

/** Library step: name plus template pick, then Create layout. */
export function LibraryStep({
  onBack,
  onCancel,
  isPending,
  rootError,
}: {
  onBack: () => void;
  onCancel: () => void;
  isPending: boolean;
  rootError?: string;
}) {
  const { control, setValue } = useFormContext<NewLayoutFormValues>();
  const name = useWatch<NewLayoutFormValues, 'name'>({ control, name: 'name' });
  const selectedSlug = useWatch<NewLayoutFormValues, 'templateSlug'>({ control, name: 'templateSlug' });

  function selectTemplate(slug: string | null) {
    setValue('templateSlug', slug, { shouldValidate: true });
  }

  const disabled = '' === (name ?? '').trim() || !selectedSlug;

  return (
    <div className="flex flex-col gap-5">
      <NameField />

      <TemplatePicker selectedSlug={selectedSlug} onSelect={selectTemplate} />

      {rootError && <p className="text-destructive text-sm">{rootError}</p>}

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-xs">
          {__('Your existing fields and gateways are mapped into the template you pick.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={onBack}>
            {__('Back', 'yaycheckout')}
          </Button>
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

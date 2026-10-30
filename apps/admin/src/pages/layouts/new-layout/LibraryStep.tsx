import { useFormContext, useWatch } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { useTemplates } from '@/lib/queries/template.queries';
import { layoutNameSchema, type NewLayoutFormValues } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { TemplatePicker } from '@/components/shared/template-picker/TemplatePicker';
import { NameField } from '@/pages/layouts/new-layout/NameField';

/** Name plus template pick, then Next to the pages it comes with. */
export function LibraryStep({ onCancel, onNext }: { onCancel: () => void; onNext: () => void }) {
  const { control, setValue, getFieldState, trigger } = useFormContext<NewLayoutFormValues>();
  const templates = useTemplates();
  const name = useWatch<NewLayoutFormValues, 'name'>({ control, name: 'name' });
  const selectedSlug = useWatch<NewLayoutFormValues, 'templateSlug'>({ control, name: 'templateSlug' });

  function selectTemplate(slug: string | null) {
    setValue('templateSlug', slug, { shouldValidate: true });

    // Until the merchant types a name, the name follows the picked template. Not marked dirty, so the next pick replaces it.
    const template = templates.find((t) => t.slug === slug);
    if (template && !getFieldState('name').isDirty) {
      setValue('name', template.name, { shouldValidate: true });
    }
  }

  const disabled = !layoutNameSchema.safeParse(name).success || !selectedSlug;

  async function next() {
    if (await trigger()) {
      onNext();
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <NameField />

      <TemplatePicker selectedSlug={selectedSlug} onSelect={selectTemplate} />

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-end">
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={onCancel}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button type="button" disabled={disabled} onClick={next}>
            {__('Next', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </div>
  );
}

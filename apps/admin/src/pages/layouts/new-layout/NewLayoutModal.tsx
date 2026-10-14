import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useCreateLayoutDraftMutation } from '@/lib/queries/layout.queries';
import { newLayoutFormSchema, type NewLayoutFormValues } from '@/lib/schema/layout.schema';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form } from '@/components/ui/form';
import { LibraryStep } from '@/pages/layouts/new-layout/LibraryStep';
import { SourceStep } from '@/pages/layouts/new-layout/SourceStep';

type Step = 'source' | 'library';

const DEFAULT_VALUES: NewLayoutFormValues = {
  name: __('Untitled checkout', 'yaycheckout'),
  source: 'default',
  templateSlug: null,
};

/**
 * The "New layout" flow: name and source, then a template when the source is the library. State lives
 * in NewLayoutForm inside DialogContent: Radix unmounts it on close, so each open starts fresh.
 */
export function NewLayoutModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      {/* The library step widens the dialog for its preview cards; the step lives in NewLayoutForm so closing resets it. */}
      <DialogContent className="sm:max-w-2xl sm:has-data-[step=library]:max-w-5xl">
        <NewLayoutForm onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  );
}

function NewLayoutForm({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState<Step>('source');

  const form = useForm<NewLayoutFormValues>({
    resolver: zodResolver(newLayoutFormSchema),
    defaultValues: DEFAULT_VALUES,
  });

  const mutation = useCreateLayoutDraftMutation();

  // Must reset `source` too, or Create layout would submit a stale library selection. A name the
  // template filled in goes back to the default; a name the merchant typed stays.
  function backToSource() {
    form.setValue('source', 'default');
    if (!form.getFieldState('name').isDirty) {
      form.setValue('name', DEFAULT_VALUES.name, { shouldValidate: true });
    }
    setStep('source');
  }

  async function onValid(values: NewLayoutFormValues) {
    const payload =
      values.source === 'default'
        ? { name: values.name, source: 'default' as const }
        : { name: values.name, source: 'library' as const, templateSlug: values.templateSlug! };

    try {
      const created = await mutation.mutateAsync(payload);
      toast.success(`${__('Layout created.', 'yaycheckout')} "${created.title}"`);
      onClose();
    } catch (error) {
      form.setError('root', { type: 'server', message: await getErrorMsg(error) });
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          {step === 'library' && (
            <button
              type="button"
              onClick={backToSource}
              className="text-muted-foreground hover:text-foreground text-xs font-normal"
            >
              ‹ {__('New layout', 'yaycheckout')}
            </button>
          )}
          {step === 'library' ? __('From library', 'yaycheckout') : __('New layout', 'yaycheckout')}
        </DialogTitle>
      </DialogHeader>

      <Form {...form}>
        <form data-step={step} onSubmit={form.handleSubmit(onValid)}>
          {step === 'source' ? (
            <SourceStep
              onChooseLibrary={() => setStep('library')}
              onCancel={onClose}
              isPending={mutation.isPending}
              rootError={form.formState.errors.root?.message}
            />
          ) : (
            <LibraryStep
              onBack={backToSource}
              onCancel={onClose}
              isPending={mutation.isPending}
              rootError={form.formState.errors.root?.message}
            />
          )}
        </form>
      </Form>
    </>
  );
}

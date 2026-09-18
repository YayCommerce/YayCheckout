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
  source: 'blank',
  templateSlug: null,
};

/**
 * The two-step "New layout" flow: Blank vs From library, then name.
 *
 * State lives in NewLayoutForm inside DialogContent: Radix unmounts it after
 * the close animation, so each open starts fresh without a reset effect.
 */
export function NewLayoutModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
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

  // Must reset `source` too, or Create draft would submit a stale library selection.
  function backToSource() {
    form.setValue('source', 'blank');
    setStep('source');
  }

  async function onValid(values: NewLayoutFormValues) {
    const payload =
      values.source === 'blank'
        ? { name: values.name, source: 'blank' as const }
        : { name: values.name, source: 'library' as const, templateSlug: values.templateSlug! };

    try {
      const created = await mutation.mutateAsync(payload);
      toast.success(`${__('Layout draft created.', 'yaycheckout')} "${created.title}"`);
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
        <form onSubmit={form.handleSubmit(onValid)}>
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

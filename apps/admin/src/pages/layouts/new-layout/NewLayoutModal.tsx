import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useCreateLayoutDraftMutation } from '@/lib/queries/layout.queries';
import { isPickable, useTemplates } from '@/lib/queries/template.queries';
import { newLayoutFormSchema, type NewLayoutFormValues } from '@/lib/schema/layout.schema';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form } from '@/components/ui/form';
import { LibraryStep } from '@/pages/layouts/new-layout/LibraryStep';
import { PagesStep } from '@/pages/layouts/new-layout/PagesStep';

/**
 * The "New layout" flow: name and template, then a preview of the checkout and thank-you pages it comes
 * with, then the new draft opens in the editor. State, the step included, lives in NewLayoutForm inside
 * DialogContent: Radix unmounts it on close, so each open starts fresh.
 */
export function NewLayoutModal({
  open,
  onOpenChange,
  onCreated,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: (id: number) => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-5xl">
        <NewLayoutForm onClose={() => onOpenChange(false)} onCreated={onCreated} />
      </DialogContent>
    </Dialog>
  );
}

function NewLayoutForm({ onClose, onCreated }: { onClose: () => void; onCreated: (id: number) => void }) {
  // The first pickable template starts selected, so Create layout works in one click. Its name is not
  // marked dirty, so picking another template replaces it.
  const templates = useTemplates();
  const firstPickable = templates.find(isPickable);
  // One form across both steps, so Back keeps the name and the template.
  const [step, setStep] = useState<'library' | 'pages'>('library');
  const form = useForm<NewLayoutFormValues>({
    resolver: zodResolver(newLayoutFormSchema),
    defaultValues: {
      name: firstPickable?.name ?? __('Untitled checkout', 'yaycheckout'),
      templateSlug: firstPickable?.slug ?? null,
    },
  });

  const mutation = useCreateLayoutDraftMutation();
  const selectedSlug = form.watch('templateSlug');
  const selectedTemplate = templates.find((template) => template.slug === selectedSlug);

  async function onValid(values: NewLayoutFormValues) {
    try {
      const created = await mutation.mutateAsync({
        name: values.name,
        source: 'library',
        templateSlug: values.templateSlug!,
      });
      toast.success(`${__('Layout created.', 'yaycheckout')} "${created.title}"`);
      onClose();
      onCreated(created.id);
    } catch (error) {
      form.setError('root', { type: 'server', message: await getErrorMsg(error) });
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{__('New layout', 'yaycheckout')}</DialogTitle>
      </DialogHeader>

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onValid)}>
          {step === 'pages' && selectedTemplate ? (
            <PagesStep
              template={selectedTemplate}
              onBack={() => {
                form.clearErrors('root');
                setStep('library');
              }}
              isPending={mutation.isPending}
              rootError={form.formState.errors.root?.message}
            />
          ) : (
            <LibraryStep onCancel={onClose} onNext={() => setStep('pages')} />
          )}
        </form>
      </Form>
    </>
  );
}

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
 * Orchestrates the two-step "New layout" flow: choosing Blank vs From
 * library, then — for From library — picking a template. One
 * react-hook-form instance backs both steps; Create draft calls the real
 * POST /layouts endpoint — see LayoutRestController::create_draft().
 *
 * No duplicate-name handling: layout titles may repeat, same as WordPress
 * Pages — the post_name slug is what WordPress deduplicates, automatically,
 * once the layout is first published.
 *
 * All per-session state (step, form, mutation) lives in NewLayoutForm,
 * which sits inside DialogContent. Radix unmounts that subtree once the
 * close animation ends and mounts it fresh on the next open, so each open
 * starts from defaults without a reset effect — and without the flash a
 * reset-on-open or reset-on-close effect painted over the fade.
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

  /*
   * Back (from the header breadcrumb or LibraryStep's own Back button) must
   * also reset `source` to 'blank': changing the step alone leaves the source
   * step's radio on "From library", so its Create draft button would submit a
   * library draft with a stale template selection instead of the blank layout
   * this step promises.
   */
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

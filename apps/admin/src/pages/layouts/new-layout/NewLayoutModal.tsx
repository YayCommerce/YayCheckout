import { useEffect, useState } from 'react';
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
 * Orchestrates the two-step "New layout" flow from
 * YC-02b_New_Layout_Modal.dc.html: choosing Blank vs From library (state 9),
 * then — for From library — picking a template (states 10/10b). One
 * react-hook-form instance backs both steps; Create draft calls the real
 * POST /layouts endpoint — see LayoutRestController::create_draft().
 *
 * No duplicate-name handling: layout titles may repeat, same as WordPress
 * Pages — the post_name slug is what WordPress deduplicates, automatically,
 * once the layout is first published.
 */
export function NewLayoutModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const [step, setStep] = useState<Step>('source');

  const form = useForm<NewLayoutFormValues>({
    resolver: zodResolver(newLayoutFormSchema),
    defaultValues: DEFAULT_VALUES,
  });

  const mutation = useCreateLayoutDraftMutation();

  /*
   * Reset on open, not on close: the dialog keeps its content mounted and
   * fading out for the close animation, so resetting synchronously on close
   * flashed the source step's UI over that fade.
   */
  useEffect(() => {
    if (open) {
      setStep('source');
      form.reset(DEFAULT_VALUES);
    }
  }, [open]);

  function handleOpenChange(nextOpen: boolean) {
    if (!nextOpen) {
      mutation.reset();
    }
    onOpenChange(nextOpen);
  }

  /*
   * Back (from the header breadcrumb or LibraryStep's own Back button) only
   * changes which step renders — it never touched the form's `source` field.
   * That let the source step's radio still show "From library" checked, so
   * its Create draft button could submit a library draft using a stale
   * template selection instead of the blank layout this step promises.
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
      handleOpenChange(false);
    } catch (error) {
      form.setError('root', { type: 'server', message: await getErrorMsg(error) });
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-2xl">
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
                onCancel={() => handleOpenChange(false)}
                isPending={mutation.isPending}
                rootError={form.formState.errors.root?.message}
              />
            ) : (
              <LibraryStep
                onBack={backToSource}
                onCancel={() => handleOpenChange(false)}
                isPending={mutation.isPending}
                rootError={form.formState.errors.root?.message}
              />
            )}
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

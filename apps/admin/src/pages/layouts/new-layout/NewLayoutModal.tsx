import { useState } from 'react';
import { HTTPError } from 'ky';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useCreateLayoutDraftMutation } from '@/lib/queries/layout.queries';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LibraryStep } from '@/pages/layouts/new-layout/LibraryStep';
import { SourceStep } from '@/pages/layouts/new-layout/SourceStep';

type Step = 'source' | 'library';

export type FormError =
  { kind: 'duplicate'; message: string; suggestedName: string } | { kind: 'generic'; message: string };

async function toFormError(error: unknown): Promise<FormError> {
  if (error instanceof HTTPError && error.response.status === 409) {
    const body = await error.response.clone().json();
    if (body?.data?.suggested_name) {
      return { kind: 'duplicate', message: body.message, suggestedName: body.data.suggested_name };
    }
  }

  return { kind: 'generic', message: await getErrorMsg(error) };
}

/**
 * Orchestrates the two-step "New layout" flow from
 * YC-02b_New_Layout_Modal.dc.html: choosing Blank vs From library (state 9),
 * then — for From library — picking a template (states 10/10b). Create draft
 * calls the real POST /layouts endpoint; see LayoutRestController::create_draft().
 */
export function NewLayoutModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const [step, setStep] = useState<Step>('source');
  const [name, setName] = useState('Untitled checkout');
  const [templateSlug, setTemplateSlug] = useState<string | null>(null);
  const [formError, setFormError] = useState<FormError | null>(null);

  const mutation = useCreateLayoutDraftMutation();

  function reset() {
    setStep('source');
    setName('Untitled checkout');
    setTemplateSlug(null);
    setFormError(null);
    mutation.reset();
  }

  function handleOpenChange(nextOpen: boolean) {
    if (!nextOpen) {
      reset();
    }
    onOpenChange(nextOpen);
  }

  function handleNameChange(value: string) {
    setName(value);
    setFormError(null);
  }

  function useSuggestedName(suggestedName: string) {
    setName(suggestedName);
    setFormError(null);
  }

  async function submit(source: 'blank' | 'library') {
    setFormError(null);

    const payload =
      source === 'blank'
        ? { name, source: 'blank' as const }
        : { name, source: 'library' as const, templateSlug: templateSlug! };

    try {
      const created = await mutation.mutateAsync(payload);
      toast.success(`${__('Layout draft created.', 'yaycheckout')} "${created.name}"`);
      handleOpenChange(false);
    } catch (error) {
      setFormError(await toFormError(error));
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
                onClick={() => setStep('source')}
                className="text-muted-foreground hover:text-foreground text-xs font-normal"
              >
                ‹ {__('New layout', 'yaycheckout')}
              </button>
            )}
            {step === 'library' ? __('From library', 'yaycheckout') : __('New layout', 'yaycheckout')}
          </DialogTitle>
        </DialogHeader>

        {step === 'source' ? (
          <SourceStep
            name={name}
            onNameChange={handleNameChange}
            onChooseLibrary={() => setStep('library')}
            onCancel={() => handleOpenChange(false)}
            onSubmit={() => submit('blank')}
            onUseSuggestedName={useSuggestedName}
            isPending={mutation.isPending}
            error={formError}
          />
        ) : (
          <LibraryStep
            name={name}
            onNameChange={handleNameChange}
            selectedSlug={templateSlug}
            onSelectSlug={setTemplateSlug}
            onBack={() => setStep('source')}
            onCancel={() => handleOpenChange(false)}
            onSubmit={() => submit('library')}
            onUseSuggestedName={useSuggestedName}
            isPending={mutation.isPending}
            error={formError}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

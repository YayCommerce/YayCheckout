import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { activePresetIdFor } from '@/lib/helpers/design.helper';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useDeleteDesignPresetMutation, useDesignQuery, useSaveDesignMutation } from '@/lib/queries/design.queries';
import {
  DEFAULT_PRESET_ID,
  designFormSchema,
  type DesignForm,
  type DesignPreset,
  type DesignResponse,
} from '@/lib/schema/design.schema';
import { useRouteLeaveGuard } from '@/hooks/useRouteLeaveGuard';
import { Form } from '@/components/ui/form';
import { PreviewPane } from '@/components/shared/PreviewPane';
import { UnsavedChangesDialog } from '@/components/shared/UnsavedChangesDialog';
import PageLayout from '@/pages/PageLayout';
import { DesignActions } from './components/DesignActions';
import { PresetsCard } from './components/PresetsCard';
import { ResetConfirmModal } from './components/ResetConfirmModal';
import { SavePresetModal } from './components/SavePresetModal';
import { TokenGroups } from './components/TokenGroups';

const FORM_ID = 'design-form';

function toFormValues(design: DesignResponse): DesignForm {
  return { tokens: design.tokens, customCss: design.customCss };
}

export default function DesignPage() {
  // initialData from the bootstrap: data is defined on first render.
  const { data } = useDesignQuery();
  const saveMutation = useSaveDesignMutation();
  const deletePresetMutation = useDeleteDesignPresetMutation();
  // The preset the tokens were copied from. Kept out of the form so it never makes the form dirty on its own.
  const [presetId, setPresetId] = useState(data.presetId);
  // Null while closed; the override count the modal quotes once opened.
  const [resetCount, setResetCount] = useState<number | null>(null);
  const [savePresetOpen, setSavePresetOpen] = useState(false);

  const form = useForm<DesignForm>({
    resolver: zodResolver(designFormSchema),
    defaultValues: toFormValues(data),
  });
  const { isDirty } = form.formState;
  const tokens = form.watch('tokens');
  const activePresetId = activePresetIdFor(data.presets, presetId, tokens);

  /** Resolves true on success so the leave dialog knows whether to proceed. */
  async function save(values: DesignForm, nextPresetId: string | null): Promise<boolean> {
    if (saveMutation.isPending) return false;

    try {
      const saved = await saveMutation.mutateAsync({
        form: values,
        presetId: activePresetIdFor(data.presets, nextPresetId, values.tokens),
      });
      form.reset(toFormValues(saved));
      setPresetId(saved.presetId);
      toast.success(__('Design saved.', 'yaycheckout'));
      return true;
    } catch (error) {
      toast.error(await getErrorMsg(error));
      return false;
    }
  }

  const onSubmit = (values: DesignForm) => save(values, presetId);

  // keepDefaultValues keeps dirtiness measured against the last save, so Save stays enabled.
  function applyPreset(preset: DesignPreset) {
    form.reset({ tokens: preset.tokens, customCss: form.getValues('customCss') }, { keepDefaultValues: true });
    setPresetId(preset.id);
  }

  async function resetToTheme() {
    const saved = await save({ tokens: data.defaultTokens, customCss: form.getValues('customCss') }, DEFAULT_PRESET_ID);
    if (saved) setResetCount(null);
  }

  // Preset writes never touch the saved tokens or CSS, so the form is left alone.
  function deletePreset(id: string) {
    deletePresetMutation.mutate(id, {
      onSuccess: () => {
        if (presetId === id) setPresetId(null);
        toast.success(__('Preset deleted.', 'yaycheckout'));
      },
    });
  }

  const { showDialog, confirmLeave, cancelLeave } = useRouteLeaveGuard(isDirty);

  return (
    <Form {...form}>
      <PageLayout
        heading={__('Design', 'yaycheckout')}
        description={__('Customize checkout colors, typography and spacing.', 'yaycheckout')}
        actions={<DesignActions formId={FORM_ID} isPending={saveMutation.isPending} onReset={setResetCount} />}
      >
        <form id={FORM_ID} onSubmit={form.handleSubmit(onSubmit)}>
          <div className="flex flex-col gap-5 lg:flex-row">
            <div className="flex w-full shrink-0 flex-col gap-3 lg:w-110">
              <PresetsCard
                presets={data.presets}
                activePresetId={activePresetId}
                deleting={deletePresetMutation.isPending}
                onApply={applyPreset}
                onDelete={deletePreset}
                onSaveCurrent={() => setSavePresetOpen(true)}
              />
              <TokenGroups />
            </div>

            <PreviewPane />
          </div>
        </form>

        <SavePresetModal
          open={savePresetOpen}
          tokens={tokens}
          onOpenChange={setSavePresetOpen}
          onSaved={(id) => {
            setPresetId(id);
            toast.success(__('Preset saved.', 'yaycheckout'));
          }}
        />

        <ResetConfirmModal
          open={null !== resetCount}
          overrideCount={resetCount ?? 0}
          isPending={saveMutation.isPending}
          onOpenChange={(open) => {
            if (!open) setResetCount(null);
          }}
          onConfirm={resetToTheme}
        />

        <UnsavedChangesDialog
          open={showDialog}
          onDiscard={confirmLeave}
          onSave={async () => {
            // A failed save stays put so the edits are not lost.
            let saved = false;
            await form.handleSubmit(async (values) => {
              saved = await onSubmit(values);
            })();
            if (saved) confirmLeave();
            else cancelLeave();
          }}
          onOpenChange={(open) => {
            if (!open) cancelLeave();
          }}
        />
      </PageLayout>
    </Form>
  );
}

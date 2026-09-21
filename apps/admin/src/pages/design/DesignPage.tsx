import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { Plus } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useDesignQuery, useSaveDesignMutation } from '@/lib/queries/design.queries';
import { designFormSchema, type DesignForm, type DesignResponse, type DesignTokens } from '@/lib/schema/design.schema';
import { useRouteLeaveGuard } from '@/hooks/useRouteLeaveGuard';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { ProGate, ProTag } from '@/components/pro/ProGate';
import { UnsavedChangesDialog } from '@/components/shared/UnsavedChangesDialog';
import PageLayout from '@/pages/PageLayout';
import { DesignActions } from './components/DesignActions';
import { PreviewPane } from './components/PreviewPane';
import { ResetConfirmModal } from './components/ResetConfirmModal';
import { TokenGroups } from './components/TokenGroups';

const FORM_ID = 'design-form';

function toFormValues(design: DesignResponse): DesignForm {
  return { tokens: design.tokens, presetId: design.presetId, customCss: design.customCss };
}

export default function DesignPage() {
  // initialData from the bootstrap: data is defined on first render.
  const { data } = useDesignQuery();
  const saveMutation = useSaveDesignMutation();
  // Null while closed; the override count the modal quotes once opened.
  const [resetCount, setResetCount] = useState<number | null>(null);

  const form = useForm<DesignForm>({
    resolver: zodResolver(designFormSchema),
    defaultValues: toFormValues(data),
  });
  const { isDirty } = form.formState;

  /** Resolves true on success so the leave dialog knows whether to proceed. */
  async function onSubmit(values: DesignForm): Promise<boolean> {
    if (saveMutation.isPending) return false;

    try {
      const saved = await saveMutation.mutateAsync(values);
      form.reset(toFormValues(saved));
      toast.success(__('Design saved.', 'yaycheckout'));
      return true;
    } catch (error) {
      toast.error(await getErrorMsg(error));
      return false;
    }
  }

  // keepDefaultValues keeps dirtiness measured against the last save, so Save stays enabled.
  function applyPreset(tokens: DesignTokens, presetId: string) {
    form.reset({ tokens, presetId, customCss: form.getValues('customCss') }, { keepDefaultValues: true });
  }

  function resetToTheme() {
    applyPreset(data.defaultTokens, 'default');
    setResetCount(null);
  }

  const { showDialog, confirmLeave, cancelLeave } = useRouteLeaveGuard(isDirty);

  return (
    <PageLayout
      heading={__('Design', 'yaycheckout')}
      description={__('Customize checkout colors, typography and spacing.', 'yaycheckout')}
      actions={
        <Form {...form}>
          <DesignActions
            formId={FORM_ID}
            defaultTokens={data.defaultTokens}
            updatedAt={data.updatedAt}
            isPending={saveMutation.isPending}
            onReset={setResetCount}
          />
        </Form>
      }
    >
      <Form {...form}>
        <form id={FORM_ID} onSubmit={form.handleSubmit(onSubmit)}>
          <div className="flex flex-col gap-5 lg:flex-row">
            <div className="flex w-full shrink-0 flex-col gap-3 lg:w-100">
              <ProGate>
                <Card className="gap-3 p-4">
                  <div className="flex items-center gap-2">
                    <span className="flex-1 text-sm font-semibold">{__('Presets', 'yaycheckout')}</span>
                    <ProTag />
                  </div>
                  <div className="flex flex-wrap gap-1.5">
                    {data.presets.map((preset) => (
                      <span
                        key={preset.id}
                        className="bg-background inline-flex h-7 items-center rounded-full border px-3 font-mono text-xs"
                      >
                        {preset.name}
                      </span>
                    ))}
                  </div>
                  <Button type="button" variant="outline" size="sm" className="self-start" disabled>
                    <Plus />
                    {__('Save current as preset', 'yaycheckout')}
                  </Button>
                </Card>
              </ProGate>

              <TokenGroups />
            </div>

            <PreviewPane />
          </div>
        </form>
      </Form>

      <ResetConfirmModal
        open={null !== resetCount}
        overrideCount={resetCount ?? 0}
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
  );
}

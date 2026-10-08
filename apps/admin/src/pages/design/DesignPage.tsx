import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useDesignQuery, useSaveDesignMutation } from '@/lib/queries/design.queries';
import { designFormSchema, type DesignForm, type DesignResponse } from '@/lib/schema/design.schema';
import { useRouteLeaveGuard } from '@/hooks/useRouteLeaveGuard';
import { Form } from '@/components/ui/form';
import { PreviewPane } from '@/components/shared/PreviewPane';
import { UnsavedChangesDialog } from '@/components/shared/UnsavedChangesDialog';
import PageLayout from '@/pages/PageLayout';
import { DesignActions } from './components/DesignActions';
import { ResetConfirmModal } from './components/ResetConfirmModal';
import { TokenGroups } from './components/TokenGroups';

const FORM_ID = 'design-form';
const HEADING = __('Design', 'yaycheckout');
const DESCRIPTION = __('Customize checkout colors and typography.', 'yaycheckout');

function toFormValues(design: DesignResponse): DesignForm {
  return { tokens: design.tokens, customCss: design.customCss };
}

export default function DesignPage() {
  // Defined on first render unless the bootstrap was rejected and is being refetched.
  const { data, error } = useDesignQuery();
  if (data) return <DesignPageBody data={data} />;

  return (
    <PageLayout heading={HEADING} description={DESCRIPTION}>
      {error && <p className="text-destructive text-sm">{error.message}</p>}
    </PageLayout>
  );
}

function DesignPageBody({ data }: { data: DesignResponse }) {
  const saveMutation = useSaveDesignMutation();
  // Null while closed; the override count the modal quotes once opened.
  const [resetCount, setResetCount] = useState<number | null>(null);

  const form = useForm<DesignForm>({
    resolver: zodResolver(designFormSchema),
    defaultValues: toFormValues(data),
  });
  const { isDirty } = form.formState;

  /** Resolves true on success so the leave dialog knows whether to proceed. */
  async function save(values: DesignForm): Promise<boolean> {
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

  const onSubmit = (values: DesignForm) => save(values);

  async function resetToTheme() {
    const saved = await save({ tokens: data.defaultTokens, customCss: form.getValues('customCss') });
    if (saved) setResetCount(null);
  }

  const { showDialog, confirmLeave, cancelLeave } = useRouteLeaveGuard(isDirty);

  return (
    <Form {...form}>
      <PageLayout
        heading={HEADING}
        description={DESCRIPTION}
        actions={
          <DesignActions design={data} formId={FORM_ID} isPending={saveMutation.isPending} onReset={setResetCount} />
        }
      >
        <form id={FORM_ID} onSubmit={form.handleSubmit(onSubmit)}>
          <div className="flex flex-col gap-5 lg:flex-row">
            <div className="flex w-full shrink-0 flex-col gap-3 lg:w-110">
              <TokenGroups />
            </div>

            <PreviewPane />
          </div>
        </form>

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

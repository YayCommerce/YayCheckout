import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { Plus, X } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { isPro } from '@/lib/plan';
import { useDeleteDesignPresetMutation, useDesignQuery, useSaveDesignMutation } from '@/lib/queries/design.queries';
import {
  designFormSchema,
  type DesignForm,
  type DesignPreset,
  type DesignResponse,
  type DesignTokens,
  type TokenSection,
} from '@/lib/schema/design.schema';
import { cn } from '@/lib/utils';
import { useRouteLeaveGuard } from '@/hooks/useRouteLeaveGuard';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Popover, PopoverClose, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ProTag } from '@/components/pro/ProGate';
import { UnsavedChangesDialog } from '@/components/shared/UnsavedChangesDialog';
import PageLayout from '@/pages/PageLayout';
import { DesignActions } from './components/DesignActions';
import { PreviewPane } from './components/PreviewPane';
import { ResetConfirmModal } from './components/ResetConfirmModal';
import { SavePresetModal } from './components/SavePresetModal';
import { TokenGroups } from './components/TokenGroups';

const FORM_ID = 'design-form';

function toFormValues(design: DesignResponse): DesignForm {
  return { tokens: design.tokens, presetId: design.presetId, customCss: design.customCss };
}

function sameTokens(a: DesignTokens, b: DesignTokens): boolean {
  return (Object.keys(a) as TokenSection[]).every((section) => {
    const [x, y] = [a[section], b[section]] as Record<string, unknown>[];
    return Object.keys(x).every((key) => x[key] === y[key]);
  });
}

/** A preset counts as applied only while the tokens still match it; a hand edit silently leaves it. */
function activePresetIdFor(presets: DesignPreset[], presetId: string | null, tokens: DesignTokens): string | null {
  const preset = presets.find((preset) => preset.id === presetId && sameTokens(preset.tokens, tokens));
  return preset?.id ?? null;
}

export default function DesignPage() {
  // initialData from the bootstrap: data is defined on first render.
  const { data } = useDesignQuery();
  const saveMutation = useSaveDesignMutation();
  // Null while closed; the override count the modal quotes once opened.
  const [resetCount, setResetCount] = useState<number | null>(null);
  const [savePresetOpen, setSavePresetOpen] = useState(false);
  const deletePresetMutation = useDeleteDesignPresetMutation();

  const form = useForm<DesignForm>({
    resolver: zodResolver(designFormSchema),
    defaultValues: toFormValues(data),
  });
  const { isDirty } = form.formState;
  const presetId = form.watch('presetId');
  const tokens = form.watch('tokens');
  // A preset counts as applied only while the tokens still match it; a hand edit silently leaves it.
  const activePresetId = activePresetIdFor(data.presets, presetId, tokens);

  /** Resolves true on success so the leave dialog knows whether to proceed. */
  async function onSubmit(values: DesignForm): Promise<boolean> {
    if (saveMutation.isPending) return false;

    try {
      const saved = await saveMutation.mutateAsync({
        ...values,
        presetId: activePresetIdFor(data.presets, values.presetId, values.tokens),
      });
      form.reset(toFormValues(saved));
      toast.success(__('Design saved.', 'yaycheckout'));
      return true;
    } catch (error) {
      toast.error(await getErrorMsg(error));
      return false;
    }
  }

  // keepDefaultValues keeps dirtiness measured against the last save, so Save stays enabled.
  function applyPreset(tokens: DesignTokens, presetId: string | null) {
    form.reset({ tokens, presetId, customCss: form.getValues('customCss') }, { keepDefaultValues: true });
  }

  async function resetToTheme() {
    const saved = await onSubmit({
      tokens: data.defaultTokens,
      presetId: null,
      customCss: form.getValues('customCss'),
    });
    if (saved) setResetCount(null);
  }

  function deletePreset(id: string) {
    deletePresetMutation.mutate(id, {
      onSuccess: (saved) => {
        if (!form.formState.isDirty) form.reset(toFormValues(saved));
        else if (presetId === id) form.setValue('presetId', saved.presetId, { shouldDirty: true });
        toast.success(__('Preset deleted.', 'yaycheckout'));
      },
    });
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
            <div className="flex w-full shrink-0 flex-col gap-3 lg:w-110">
              {/* Free sees the presets but cannot use them: the PRO tag says why. */}
              <Card className={cn('gap-3 p-4', !isPro && 'opacity-60')}>
                <div className="flex items-center gap-2">
                  <span className="flex-1 text-sm font-semibold">{__('Presets', 'yaycheckout')}</span>
                  <ProTag />
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {data.presets
                    .filter((preset) => isPro || preset.builtin)
                    .map((preset) => (
                      <span
                        key={preset.id}
                        className={cn(
                          'transition-default inline-flex h-7 items-center rounded-full border text-xs',
                          preset.id === activePresetId
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-background hover:bg-accent',
                        )}
                      >
                        <button
                          type="button"
                          onClick={() => applyPreset(preset.tokens, preset.id)}
                          disabled={!isPro}
                          className={cn('cursor-pointer px-3 disabled:cursor-default', !preset.builtin && 'pe-1')}
                        >
                          {preset.name}
                        </button>
                        {!preset.builtin && (
                          <Popover>
                            <PopoverTrigger asChild>
                              <button
                                type="button"
                                aria-label={__('Delete preset', 'yaycheckout')}
                                disabled={!isPro}
                                className="cursor-pointer pe-2 opacity-60 hover:opacity-100 disabled:cursor-default"
                              >
                                <X className="size-3" />
                              </button>
                            </PopoverTrigger>
                            <PopoverContent align="start" className="flex w-auto flex-col gap-3 p-3">
                              <p className="text-xs">
                                {sprintf(
                                  /* translators: %s: preset name */
                                  __('Delete preset "%s"?', 'yaycheckout'),
                                  preset.name,
                                )}
                              </p>
                              <div className="flex justify-end gap-2">
                                <PopoverClose asChild>
                                  <Button type="button" variant="outline" size="sm" className="h-7 px-2.5 text-xs">
                                    {__('Cancel', 'yaycheckout')}
                                  </Button>
                                </PopoverClose>
                                <PopoverClose asChild>
                                  <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    className="h-7 px-2.5 text-xs"
                                    onClick={() => deletePreset(preset.id)}
                                    disabled={deletePresetMutation.isPending}
                                  >
                                    {__('Delete', 'yaycheckout')}
                                  </Button>
                                </PopoverClose>
                              </div>
                            </PopoverContent>
                          </Popover>
                        )}
                      </span>
                    ))}
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="self-start"
                  disabled={!isPro}
                  onClick={() => setSavePresetOpen(true)}
                >
                  <Plus />
                  {__('Save current as preset', 'yaycheckout')}
                </Button>
              </Card>

              <TokenGroups />
            </div>

            <PreviewPane />
          </div>
        </form>
      </Form>

      <SavePresetModal
        open={savePresetOpen}
        tokens={form.getValues('tokens')}
        onOpenChange={setSavePresetOpen}
        onSaved={(saved, id) => {
          if (!form.formState.isDirty) form.reset(toFormValues(saved));
          else form.setValue('presetId', id, { shouldDirty: true });
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
  );
}

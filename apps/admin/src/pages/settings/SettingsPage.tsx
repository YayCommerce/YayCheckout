import { zodResolver } from '@hookform/resolvers/zod';
import * as TabsPrimitive from '@radix-ui/react-tabs';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useSaveSettingsMutation, useSettingsQuery } from '@/lib/queries/settings.queries';
import { settingsSchema, type Settings } from '@/lib/schema/settings.schema';
import { useRouteLeaveGuard } from '@/hooks/useRouteLeaveGuard';
import { Form } from '@/components/ui/form';
import { SideNavMenuItem, SideNavMenuList } from '@/components/ui/navmenu-side';
import { UnsavedChangesDialog } from '@/components/shared/UnsavedChangesDialog';
import PageLayout from '@/pages/PageLayout';
import { AdvancedTab } from './components/AdvancedTab';
import { GeneralTab } from './components/GeneralTab';
import { IntegrationsTab } from './components/IntegrationsTab';
import { SettingsFooter } from './components/SettingsFooter';
import { SETTINGS_SUB_MENUS, type SettingsSubMenu } from './settings-sub-menus';

const TAB_LABELS: Record<SettingsSubMenu, string> = {
  general: __('General', 'yaycheckout'),
  integrations: __('Integrations', 'yaycheckout'),
  advanced: __('Advanced', 'yaycheckout'),
};

const TAB_PANEL_CLASS = 'bg-card rounded-md border';

// One react-hook-form spans all tabs.
export default function SettingsPage() {
  const { subMenu } = useParams<{ subMenu: SettingsSubMenu }>();
  const activeTab: SettingsSubMenu = subMenu ?? 'general';
  const navigate = useNavigate();

  // initialData from the bootstrap: data is defined on first render.
  const { data } = useSettingsQuery();
  const saveMutation = useSaveSettingsMutation();

  const form = useForm<Settings>({
    resolver: zodResolver(settingsSchema),
    defaultValues: data.settings,
  });
  const { isDirty } = form.formState;

  /** Resolves true on success so the leave dialog knows whether to proceed. */
  async function onSubmit(values: Settings): Promise<boolean> {
    if (saveMutation.isPending) return false;

    try {
      const saved = await saveMutation.mutateAsync(values);
      form.reset(saved.settings);
      toast.success(__('Settings saved.', 'yaycheckout'));
      return true;
    } catch (error) {
      toast.error(await getErrorMsg(error));
      return false;
    }
  }

  const { showDialog, confirmLeave, cancelLeave } = useRouteLeaveGuard(isDirty, ['/settings/*']);

  return (
    <PageLayout
      heading={__('Settings', 'yaycheckout')}
      description={__('Configure checkout behaviour, integrations and advanced options.', 'yaycheckout')}
    >
      <Form {...form}>
        <form id="settings-form" onSubmit={form.handleSubmit(onSubmit)}>
          <TabsPrimitive.Root value={activeTab} onValueChange={(value) => navigate(`/settings/${value}`)}>
            <div className="flex flex-col gap-4 sm:flex-row">
              <div className="w-full sm:w-37.5">
                <TabsPrimitive.List asChild>
                  <SideNavMenuList mode="tabs" className="flex w-full flex-wrap items-start justify-start sm:flex-col">
                    {SETTINGS_SUB_MENUS.map((tab) => (
                      <TabsPrimitive.Trigger key={tab} value={tab} asChild>
                        <SideNavMenuItem className="w-fit p-3 sm:w-full">{TAB_LABELS[tab]}</SideNavMenuItem>
                      </TabsPrimitive.Trigger>
                    ))}
                  </SideNavMenuList>
                </TabsPrimitive.List>
              </div>

              <div className="flex min-w-0 flex-1 flex-col gap-4">
                <TabsPrimitive.Content value="general" className={TAB_PANEL_CLASS}>
                  <GeneralTab />
                </TabsPrimitive.Content>
                <TabsPrimitive.Content value="integrations" className={TAB_PANEL_CLASS}>
                  <IntegrationsTab />
                </TabsPrimitive.Content>
                <TabsPrimitive.Content value="advanced" className={TAB_PANEL_CLASS}>
                  <AdvancedTab />
                </TabsPrimitive.Content>

                <SettingsFooter activeTab={activeTab} updatedAt={data.updatedAt} isPending={saveMutation.isPending} />
              </div>
            </div>
          </TabsPrimitive.Root>
        </form>
      </Form>

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

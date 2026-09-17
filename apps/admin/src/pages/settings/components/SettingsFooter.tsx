import { useFormContext } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import type { Settings } from '@/lib/schema/settings.schema';
import { LoadingButton } from '@/components/ui/button';
import type { SettingsSubMenu } from '../settings-sub-menus';

/** Count the `true` leaves of react-hook-form's nested dirtyFields map. */
function countDirtyLeaves(value: unknown): number {
  if (value === true) return 1;
  if (value && typeof value === 'object') {
    return Object.values(value).reduce<number>((total, child) => total + countDirtyLeaves(child), 0);
  }
  return 0;
}

const lastSavedFormat = new Intl.DateTimeFormat(undefined, {
  day: 'numeric',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
});

/*
 * Status text on the left, Save on the right — the wireframe's panel footer.
 * One instance serves every tab: the form spans all of them, so "changes
 * pending" counts edits on any tab, and Save is disabled only when nothing
 * anywhere has changed or a save is in flight.
 */
export function SettingsFooter({
  activeTab,
  updatedAt,
  isPending,
}: {
  activeTab: SettingsSubMenu;
  updatedAt: string | null;
  isPending: boolean;
}) {
  // Read dirtyFields during render so react-hook-form's proxy subscribes to it.
  const { formState } = useFormContext<Settings>();
  const dirtyCount = countDirtyLeaves(formState.dirtyFields);
  const isDirty = dirtyCount > 0;

  let status: string;
  if (isDirty) {
    /* translators: %d: number of edited settings not yet saved */
    status = sprintf(_n('%d change pending', '%d changes pending', dirtyCount, 'yaycheckout'), dirtyCount);
  } else if (activeTab === 'integrations') {
    status = __('nothing to save on this tab', 'yaycheckout');
  } else if (updatedAt) {
    /* translators: %s: date and time of the last save */
    status = sprintf(__('last saved %s', 'yaycheckout'), lastSavedFormat.format(new Date(updatedAt)));
  } else {
    status = __('not saved yet', 'yaycheckout');
  }

  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-muted-foreground font-mono text-xs">{status}</span>
      <LoadingButton type="submit" loading={isPending} disabled={!isDirty || isPending}>
        {__('Save', 'yaycheckout')}
      </LoadingButton>
    </div>
  );
}

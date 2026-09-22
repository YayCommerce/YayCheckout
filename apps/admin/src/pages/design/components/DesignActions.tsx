import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { formatDateTime } from '@/lib/helpers/date.helper';
import { countTokenDiffs } from '@/lib/helpers/design.helper';
import { countDirtyLeaves } from '@/lib/helpers/form.helper';
import { useDesignQuery } from '@/lib/queries/design.queries';
import type { DesignForm } from '@/lib/schema/design.schema';
import { Button, LoadingButton } from '@/components/ui/button';

/**
 * Top-bar Reset/Save. Lives outside the <form>, so Save targets it by id.
 * onReset receives how many tokens differ from the theme, for the confirm copy.
 */
export function DesignActions({
  formId,
  isPending,
  onReset,
}: {
  formId: string;
  isPending: boolean;
  onReset: (overrideCount: number) => void;
}) {
  // Read during render so react-hook-form's proxy subscribes to dirtyFields.
  const { control, formState } = useFormContext<DesignForm>();
  const { data } = useDesignQuery();
  const tokens = useWatch({ control, name: 'tokens' });
  const dirtyCount = countDirtyLeaves(formState.dirtyFields);
  const { isDirty } = formState;
  // What "Reset to theme" would clear.
  const overrideCount = countTokenDiffs(tokens, data.defaultTokens);

  let status: string;
  if (isDirty) {
    /* translators: %d: number of edits not yet saved */
    status = sprintf(_n('%d change pending', '%d changes pending', dirtyCount, 'yaycheckout'), dirtyCount);
  } else if (data.updatedAt) {
    /* translators: %s: date and time of the last save */
    status = sprintf(__('last saved %s', 'yaycheckout'), formatDateTime(data.updatedAt));
  } else {
    status = __('not saved yet', 'yaycheckout');
  }

  return (
    <>
      <span className="text-muted-foreground text-sm">{status}</span>
      <Button
        type="button"
        variant="link"
        size="sm"
        onClick={() => onReset(overrideCount)}
        disabled={0 === overrideCount}
      >
        {__('Reset to theme', 'yaycheckout')}
      </Button>
      <LoadingButton type="submit" form={formId} loading={isPending} disabled={!isDirty || isPending}>
        {__('Save', 'yaycheckout')}
      </LoadingButton>
    </>
  );
}

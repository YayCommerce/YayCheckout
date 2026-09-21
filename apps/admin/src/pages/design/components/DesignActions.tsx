import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { formatDateTime } from '@/lib/helpers/date.helper';
import { countDirtyLeaves } from '@/lib/helpers/form.helper';
import type { DesignForm, DesignTokens } from '@/lib/schema/design.schema';
import { Button, LoadingButton } from '@/components/ui/button';

/** Tokens that differ from the theme defaults — what "Reset to theme" would clear. */
function countOverrides(tokens: DesignTokens, defaults: DesignTokens): number {
  return (Object.keys(tokens) as (keyof DesignTokens)[]).reduce((total, section) => {
    const current = tokens[section] as Record<string, unknown>;
    const base = defaults[section] as Record<string, unknown>;
    return total + Object.keys(current).filter((key) => current[key] !== base[key]).length;
  }, 0);
}

/**
 * Top-bar Reset/Save. Lives outside the <form>, so Save targets it by id.
 * onReset receives how many tokens differ from the theme, for the confirm copy.
 */
export function DesignActions({
  formId,
  defaultTokens,
  updatedAt,
  isPending,
  onReset,
}: {
  formId: string;
  defaultTokens: DesignTokens;
  updatedAt: string | null;
  isPending: boolean;
  onReset: (overrideCount: number) => void;
}) {
  // Read during render so react-hook-form's proxy subscribes to dirtyFields.
  const { control, formState } = useFormContext<DesignForm>();
  const tokens = useWatch({ control, name: 'tokens' });
  const dirtyCount = countDirtyLeaves(formState.dirtyFields);
  const { isDirty } = formState;
  const overrideCount = countOverrides(tokens, defaultTokens);

  let status: string;
  if (isDirty) {
    /* translators: %d: number of edits not yet saved */
    status = sprintf(_n('%d change pending', '%d changes pending', dirtyCount, 'yaycheckout'), dirtyCount);
  } else if (updatedAt) {
    /* translators: %s: date and time of the last save */
    status = sprintf(__('last saved %s', 'yaycheckout'), formatDateTime(updatedAt));
  } else {
    status = __('not saved yet', 'yaycheckout');
  }

  return (
    <>
      <span className="text-muted-foreground font-mono text-xs">{status}</span>
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

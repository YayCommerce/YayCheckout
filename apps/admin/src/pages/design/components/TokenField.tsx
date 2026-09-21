import type { ReactNode } from 'react';
import { useFormContext, useWatch, type ControllerRenderProps, type FieldPath } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { useDesignQuery } from '@/lib/queries/design.queries';
import type { DesignForm, DesignTokens, TokenSection } from '@/lib/schema/design.schema';
import { cn } from '@/lib/utils';
import { FormField } from '@/components/ui/form';

type TokenPath<S extends TokenSection, K extends keyof DesignTokens[S] & string> = `tokens.${S}.${K}` &
  FieldPath<DesignForm>;

/**
 * One token row: label, the control (rendered from the form field), and a
 * trailing tag — "changed" while edited since the last save, "(from theme)"
 * while still the Default value, else none.
 */
export function TokenField<S extends TokenSection, K extends keyof DesignTokens[S] & string>({
  section,
  token,
  label,
  children,
}: {
  section: S;
  token: K;
  label: string;
  children: (field: ControllerRenderProps<DesignForm, TokenPath<S, K>>) => ReactNode;
}) {
  const { control, formState, getFieldState } = useFormContext<DesignForm>();
  const { data } = useDesignQuery();

  // Every section/token pair in the schema is a real path; the generic just can't prove it.
  const name = `tokens.${section}.${token}` as TokenPath<S, K>;
  const value = useWatch({ control, name });
  const isDirty = getFieldState(name, formState).isDirty;
  const isDefault = value === data.defaultTokens[section][token];
  const tag = isDirty ? __('changed', 'yaycheckout') : isDefault ? __('(from theme)', 'yaycheckout') : null;

  return (
    <div className={cn('flex items-center gap-3 border-b px-4 py-2.5 last:border-b-0', isDirty && 'bg-muted-400')}>
      <div className={cn('w-24 shrink-0 text-sm', isDirty && 'font-semibold')}>{label}</div>
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <FormField control={control} name={name} render={({ field }) => <>{children(field)}</>} />
      </div>
      {tag ? <span className={cn('shrink-0 text-xs', !isDirty && 'text-muted-foreground')}>{tag}</span> : null}
    </div>
  );
}

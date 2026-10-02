import type { ReactNode } from 'react';
import { useFormContext, type ControllerRenderProps, type FieldPath } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { DesignForm, DesignTokens, TokenSection } from '@/lib/schema/design.schema';
import { cn } from '@/lib/utils';
import { FormField } from '@/components/ui/form';

type TokenPath<S extends TokenSection, K extends keyof DesignTokens[S] & string> = `tokens.${S}.${K}` &
  FieldPath<DesignForm>;

/**
 * One token row: label, the control (rendered from the form field), and a
 * trailing "changed" tag while edited since the last save.
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

  // Every section/token pair in the schema is a real path; the generic just can't prove it.
  const name = `tokens.${section}.${token}` as TokenPath<S, K>;
  const isDirty = getFieldState(name, formState).isDirty;

  return (
    <div className={cn('flex items-center gap-3 border-b px-4 py-2.5 last:border-b-0', isDirty && 'bg-muted-400')}>
      <div className={cn('w-24 shrink-0 text-sm', isDirty && 'font-semibold')}>{label}</div>
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <FormField control={control} name={name} render={({ field }) => <>{children(field)}</>} />
      </div>
      {isDirty ? <span className="shrink-0 text-xs">{__('changed', 'yaycheckout')}</span> : null}
    </div>
  );
}

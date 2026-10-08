import type { ReactNode } from 'react';
import { RotateCcw } from 'lucide-react';
import { useFormContext, type ControllerRenderProps, type FieldPath } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { DesignForm, DesignTokens, TokenSection } from '@/lib/schema/design.schema';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

type TokenPath<S extends TokenSection, K extends keyof DesignTokens[S] & string> = `tokens.${S}.${K}` &
  FieldPath<DesignForm>;

/**
 * One token row, with a revert button while the token differs from the last save.
 * Revert restores the last save, not the value of an applied preset.
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
  const { control, formState, getFieldState, resetField } = useFormContext<DesignForm>();

  // Every section/token pair in the schema is a real path; the generic just can't prove it.
  const name = `tokens.${section}.${token}` as TokenPath<S, K>;
  const isDirty = getFieldState(name, formState).isDirty;
  const revertLabel = __('Revert to saved', 'yaycheckout');

  return (
    <div className="flex items-center gap-3 border-b px-4 py-2.5 last:border-b-0">
      <div className="w-24 shrink-0 text-sm">{label}</div>
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <FormField control={control} name={name} render={({ field }) => <>{children(field)}</>} />
      </div>
      {/* Fixed-width slot so rows don't shift as the button comes and goes. */}
      <div className="size-8 shrink-0">
        {isDirty ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button variant="ghost" size="icon-sm" aria-label={revertLabel} onClick={() => resetField(name)}>
                <RotateCcw />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{revertLabel}</TooltipContent>
          </Tooltip>
        ) : null}
      </div>
    </div>
  );
}

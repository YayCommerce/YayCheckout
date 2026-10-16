import { ReactNode } from 'react';
import { TriangleAlert } from 'lucide-react';

import { Field, FieldContent, FieldDescription, FieldTitle } from '@/components/ui/field';
import { FormDescription, FormItem, FormLabel, FormMessage } from '@/components/ui/form';

/** For the rare setting that can break a working store. The icon carries the colour: --warning is too light for text. */
function Warning({ children }: { children: string }) {
  return (
    <p className="text-foreground flex items-start gap-1.5 text-xs/normal">
      <TriangleAlert className="text-warning mt-0.5 size-3.5 shrink-0" />
      {children}
    </p>
  );
}

/**
 * One form-bound setting, rendered inside FormField's render prop. Horizontal puts the label on the
 * left and the control on the right (switches); vertical stacks the label above the control (inputs).
 * Wrap the focusable element in FormControl, so the label and description point at it.
 */
export function SettingRow({
  label,
  description,
  warning,
  orientation = 'horizontal',
  children,
}: {
  label: string;
  description?: ReactNode;
  warning?: string;
  orientation?: 'horizontal' | 'vertical';
  children: ReactNode;
}) {
  if (orientation === 'vertical') {
    return (
      <FormItem>
        <FormLabel className="font-medium">{label}</FormLabel>
        {children}
        {description ? <FormDescription>{description}</FormDescription> : null}
        {warning ? <Warning>{warning}</Warning> : null}
        <FormMessage />
      </FormItem>
    );
  }

  return (
    <FormItem orientation="horizontal" className="gap-4">
      <FieldContent>
        <FormLabel className="font-medium">{label}</FormLabel>
        {description ? <FormDescription>{description}</FormDescription> : null}
        {warning ? <Warning>{warning}</Warning> : null}
        <FormMessage />
      </FieldContent>
      {children}
    </FormItem>
  );
}

/** A row with an action instead of a form value, e.g. a button or a link. */
export function SettingActionRow({
  title,
  description,
  children,
}: {
  title: string;
  description?: ReactNode;
  children: ReactNode;
}) {
  return (
    <Field orientation="horizontal" className="gap-4">
      <FieldContent>
        <FieldTitle className="text-[13px]/snug">{title}</FieldTitle>
        {description ? <FieldDescription>{description}</FieldDescription> : null}
      </FieldContent>
      <div className="flex shrink-0 items-center">{children}</div>
    </Field>
  );
}

import { cn } from '@/lib/utils';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

/** The input of a typed-phrase confirm; see useTypedConfirm(). */
export function TypedConfirmField({
  id,
  label,
  phrase,
  value,
  onChange,
  className,
  disabled,
}: {
  id: string;
  label: string;
  phrase: string;
  value: string;
  onChange: (value: string) => void;
  className?: string;
  disabled?: boolean;
}) {
  return (
    <Field>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Input
        id={id}
        className={cn('tracking-widest', className)}
        placeholder={phrase}
        autoComplete="off"
        autoFocus
        value={value}
        onChange={(event) => onChange(event.target.value)}
        disabled={disabled}
      />
    </Field>
  );
}

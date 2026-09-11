import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FormError } from '@/pages/layouts/new-layout/NewLayoutModal';

/**
 * The Name field shared by both New-layout steps (yc02-9, yc02-10/10b), plus
 * the duplicate-name recovery UI from yc02-16: the collision message, the
 * one-tap "Use '<suggestion>'" fix, and the disabling border.
 */
export function NameField({
  name,
  onNameChange,
  error,
  onUseSuggestedName,
}: {
  name: string;
  onNameChange: (value: string) => void;
  error: FormError | null;
  onUseSuggestedName: (suggestedName: string) => void;
}) {
  const isDuplicate = error?.kind === 'duplicate';

  return (
    <div className="flex max-w-md flex-col gap-1.5">
      <label className="text-sm">{__('Name', 'yaycheckout')}</label>
      <Input
        value={name}
        onChange={(e) => onNameChange(e.target.value)}
        className={cn(isDuplicate && 'border-destructive')}
      />
      {isDuplicate && (
        <div className="flex flex-col gap-1.5">
          <p className="text-destructive text-xs">{error.message}</p>
          <p className="text-muted-foreground text-xs">
            {__('Names have to be unique so preview links and the assignment list stay readable.', 'yaycheckout')}
          </p>
          <Button size="sm" variant="outline" className="w-fit" onClick={() => onUseSuggestedName(error.suggestedName)}>
            {__('Use', 'yaycheckout')} "{error.suggestedName}"
          </Button>
        </div>
      )}
    </div>
  );
}

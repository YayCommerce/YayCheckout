import { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

/** Inline external link used by the settings rows — opens in a new tab. */
export function DocLink({ className, ...props }: ComponentProps<'a'>) {
  return (
    <a
      target="_blank"
      rel="noreferrer"
      className={cn('text-primary underline-offset-4 hover:underline', className)}
      {...props}
    />
  );
}

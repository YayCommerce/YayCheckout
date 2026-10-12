import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

/** A check mark beside a heading and one line under it, for a step that succeeded. `lg` heads a whole dialog. */
export function SuccessHeader({
  title,
  description,
  size = 'sm',
}: {
  title: string;
  description: string;
  size?: 'sm' | 'lg';
}) {
  const Heading = size === 'lg' ? 'h2' : 'h3';

  return (
    <div className={cn('flex', size === 'lg' ? 'gap-4' : 'gap-3')}>
      <span
        className={cn(
          'bg-success/10 text-success flex shrink-0 items-center justify-center',
          size === 'lg' ? 'size-10 rounded-md' : 'size-8 rounded-full',
        )}
      >
        <Check className={size === 'lg' ? 'size-5' : 'size-4'} />
      </span>
      <div className="space-y-1">
        <Heading className={size === 'lg' ? 'text-lg font-semibold' : 'text-base/snug font-semibold'}>{title}</Heading>
        <p className="text-muted-foreground text-sm">{description}</p>
      </div>
    </div>
  );
}

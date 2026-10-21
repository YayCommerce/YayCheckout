import { Fragment } from 'react';
import { _n, sprintf } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

interface OverflowBadgesProps {
  /** Badge texts in groups; a flat list is one group. */
  groups: string[][];
  /** Shown between groups, such as "or". */
  joiner?: string;
  /** Badges shown before the rest fold into a "+N" badge. */
  max?: number;
  className?: string;
}

/**
 * Badges on one line that never widen their table cell: each truncates, and past `max` the rest fold into a
 * "+N" badge whose tooltip lists them all. The cap sits on the div because auto table layout ignores a cell's max-width.
 */
export function OverflowBadges({ groups, joiner, max = 2, className }: OverflowBadgesProps) {
  const total = groups.reduce((count, group) => count + group.length, 0);
  const hidden = Math.max(0, total - max);

  const shown: string[][] = [];
  for (const group of groups) {
    const room = max - shown.flat().length;
    if (room <= 0) break;
    if (group.length > 0) shown.push(group.slice(0, room));
  }

  return (
    <div className={cn('flex max-w-70 min-w-0 flex-nowrap items-center gap-1 py-2', className)}>
      {shown.map((badges, groupIndex) => (
        <Fragment key={groupIndex}>
          {groupIndex > 0 && joiner && <span className="text-muted-foreground shrink-0 text-xs">{joiner}</span>}
          {badges.map((text, badgeIndex) => (
            <Badge key={badgeIndex} variant="outline" className="max-w-40 min-w-0 shrink" title={text}>
              <span className="truncate">{text}</span>
            </Badge>
          ))}
        </Fragment>
      ))}
      {hidden > 0 && (
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge
              variant="secondary"
              tabIndex={0}
              // translators: %d: number of badges not shown.
              aria-label={sprintf(_n('%d more', '%d more', hidden, 'yaycheckout'), hidden)}
            >
              +{hidden}
            </Badge>
          </TooltipTrigger>
          <TooltipContent className="text-start">
            {groups.map((group, groupIndex) => (
              <Fragment key={groupIndex}>
                {groupIndex > 0 && joiner && <div className="opacity-70">{joiner}</div>}
                {group.map((text, index) => (
                  <div key={index}>{text}</div>
                ))}
              </Fragment>
            ))}
          </TooltipContent>
        </Tooltip>
      )}
    </div>
  );
}

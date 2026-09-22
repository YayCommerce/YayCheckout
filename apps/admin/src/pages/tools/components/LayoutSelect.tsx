import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';

import { useLayoutsQuery } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES } from '@/lib/schema/layout.schema';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';

interface LayoutSelectProps {
  value: number | null;
  onChange: (id: number) => void;
}

/** Layout picker for the Tools cards; picks the active layout once the list arrives. */
export function LayoutSelect({ value, onChange }: LayoutSelectProps) {
  const list = useLayoutsQuery({ status: LISTED_LAYOUT_STATUSES });
  const layouts = list.data?.items ?? [];

  useEffect(() => {
    if (value !== null || layouts.length === 0) return;
    onChange((layouts.find((layout) => layout.isActive) ?? layouts[0]).id);
  }, [value, layouts, onChange]);

  return (
    <div className="space-y-2">
      <Label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
        {__('Layout', 'yaycheckout')}
      </Label>
      {list.isPending ? (
        <Skeleton className="h-9 w-full" />
      ) : (
        <Select value={value === null ? undefined : String(value)} onValueChange={(next) => onChange(Number(next))}>
          <SelectTrigger className="w-full">
            <SelectValue placeholder={__('Select a layout', 'yaycheckout')} />
          </SelectTrigger>
          <SelectContent>
            {layouts.map((layout) => (
              <SelectItem key={layout.id} value={String(layout.id)}>
                {layout.title}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </div>
  );
}

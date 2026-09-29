import { TriangleAlert } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { ComboboxBadge, ComboboxIcon, ComboboxRemove, ComboboxTrigger } from '@/components/ui/combobox';

/**
 * Popover trigger for the condition value pickers: one removable badge per pick, wrapping onto more lines.
 * A pick this store lacks (imported, or deleted since) is flagged, not refused: it may exist later, and it never matches meanwhile.
 */
export function MultiSelectTrigger({
  selected,
  placeholder,
  onRemove,
  className,
}: {
  selected: { value: string; label: string; isMissing?: boolean }[];
  placeholder: string;
  onRemove: (value: string) => void;
  className?: string;
}) {
  return (
    <ComboboxTrigger className={cn('min-w-0', className)}>
      <div className={cn('flex flex-wrap gap-1', selected.length > 0 && '-ml-1')}>
        {selected.length === 0 ? (
          <span className="text-muted-foreground">{placeholder}</span>
        ) : (
          selected.map((item) => (
            <ComboboxBadge key={item.value} className={cn(item.isMissing && 'border-warning/50 bg-warning/10')}>
              {item.isMissing ? (
                <span className="flex items-center gap-1" title={__('Not found on this store', 'yaycheckout')}>
                  <TriangleAlert className="text-warning size-3" />
                  {item.label}
                </span>
              ) : (
                item.label
              )}
              <ComboboxRemove onRemove={() => onRemove(item.value)} />
            </ComboboxBadge>
          ))
        )}
      </div>
      <ComboboxIcon />
    </ComboboxTrigger>
  );
}

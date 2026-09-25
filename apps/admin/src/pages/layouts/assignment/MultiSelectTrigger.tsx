import { cn } from '@/lib/utils';
import { ComboboxBadge, ComboboxIcon, ComboboxRemove, ComboboxTrigger } from '@/components/ui/combobox';

/** Popover trigger for the condition value pickers: one removable badge per pick, wrapping onto more lines. */
export function MultiSelectTrigger({
  selected,
  placeholder,
  onRemove,
  className,
}: {
  selected: { value: string; label: string }[];
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
            <ComboboxBadge key={item.value}>
              {item.label}
              <ComboboxRemove onRemove={() => onRemove(item.value)} />
            </ComboboxBadge>
          ))
        )}
      </div>
      <ComboboxIcon />
    </ComboboxTrigger>
  );
}

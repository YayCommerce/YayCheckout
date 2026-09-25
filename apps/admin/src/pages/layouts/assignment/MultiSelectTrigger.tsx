import { cn } from '@/lib/utils';
import { ComboboxBadge, ComboboxIcon, ComboboxRemove, ComboboxTrigger } from '@/components/ui/combobox';

const MAX_BADGES = 3;

/** Popover trigger for the condition value pickers: one removable badge per pick, then "+N". */
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
          <>
            {selected.slice(0, MAX_BADGES).map((item) => (
              <ComboboxBadge key={item.value}>
                {item.label}
                <ComboboxRemove onRemove={() => onRemove(item.value)} />
              </ComboboxBadge>
            ))}
            {selected.length > MAX_BADGES && (
              <ComboboxBadge className="px-1">+{selected.length - MAX_BADGES}</ComboboxBadge>
            )}
          </>
        )}
      </div>
      <ComboboxIcon />
    </ComboboxTrigger>
  );
}

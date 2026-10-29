import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { useAssignmentOptionLabels, useAssignmentOptionSearch } from '@/lib/queries/assignment.queries';
import type { AssignmentOptionKind } from '@/lib/schema/assignment.schema';
import { cn } from '@/lib/utils';
import { useDebouncedSearch } from '@/hooks/useDebouncedSearch';
import { ComboboxCheckbox } from '@/components/ui/combobox';
import { Command, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { MultiSelectTrigger } from '@/pages/layouts/assignment/MultiSelectTrigger';

/** Multi-select over a list the server searches (products, categories). Value is the picked ids, as strings. */
export function AsyncMultiSelect({
  kind,
  value,
  onChange,
  placeholder,
  searchPlaceholder,
  emptyText,
  className,
}: {
  kind: AssignmentOptionKind;
  value: string[];
  onChange: (value: string[]) => void;
  placeholder: string;
  searchPlaceholder: string;
  emptyText: string;
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const { input, search, setInput } = useDebouncedSearch();
  const results = useAssignmentOptionSearch(kind, search, open);
  const labels = useAssignmentOptionLabels(kind, value.map(Number));
  // Labels a search already returned, so a new pick names itself before its lookup answers.
  const [seen, setSeen] = useState<Record<string, string>>({});

  function toggle(key: string, label?: string) {
    if (label !== undefined) setSeen((known) => ({ ...known, [key]: label }));
    onChange(value.includes(key) ? value.filter((picked) => picked !== key) : [...value, key]);
  }

  const options = results.data ?? [];

  return (
    // Modal: inside the Sheet's scroll lock, a non-modal popover's list does not scroll with the wheel.
    <Popover modal open={open} onOpenChange={setOpen}>
      <MultiSelectTrigger
        className={className}
        placeholder={placeholder}
        selected={value.map((key) => ({
          value: key,
          label: labels.data?.[key] ?? seen[key] ?? `#${key}`,
          // Only once the lookup has answered for this exact set: a placeholder reply lacks the newest pick.
          isMissing: labels.isSuccess && !labels.isPlaceholderData && labels.data[key] === undefined,
        }))}
        onRemove={(key) => toggle(key)}
      />
      <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
        {/* The server searches, so cmdk must show the rows it is given, in order. */}
        <Command shouldFilter={false}>
          <CommandInput placeholder={searchPlaceholder} value={input} onValueChange={setInput} />
          <CommandList className={cn('transition-opacity', results.isPlaceholderData && 'opacity-60')}>
            {options.length === 0 ? (
              <p className="text-muted-foreground py-6 text-center text-sm">
                {results.isFetching ? __('Searching…', 'yaycheckout') : emptyText}
              </p>
            ) : (
              <CommandGroup>
                {options.map((option) => {
                  const key = String(option.id);

                  return (
                    <CommandItem key={key} value={key} onSelect={() => toggle(key, option.label)}>
                      <ComboboxCheckbox selected={value.includes(key)} />
                      {option.label}
                    </CommandItem>
                  );
                })}
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

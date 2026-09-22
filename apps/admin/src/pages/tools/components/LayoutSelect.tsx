import { useEffect, useId, useState } from 'react';
import { CheckIcon } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { useLayoutsQuery } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES, type LayoutSummary } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { ComboboxIcon, ComboboxTrigger } from '@/components/ui/combobox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Field, FieldLabel } from '@/components/ui/field';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';

interface LayoutSelectProps {
  value: LayoutSummary | null;
  onChange: (layout: LayoutSummary) => void;
}

/** Searchable layout picker for the Tools cards; picks the active layout once the list arrives. */
export function LayoutSelect({ value, onChange }: LayoutSelectProps) {
  const list = useLayoutsQuery({ status: LISTED_LAYOUT_STATUSES });
  const layouts = list.data?.items ?? [];
  const [open, setOpen] = useState(false);
  const id = useId();

  useEffect(() => {
    if (value !== null || layouts.length === 0) return;
    onChange(layouts.find((layout) => layout.isActive) ?? layouts[0]);
  }, [value, layouts, onChange]);

  return (
    <Field>
      <FieldLabel htmlFor={id}>{__('Layout', 'yaycheckout')}</FieldLabel>
      {list.isPending ? (
        <Skeleton className="h-9 w-full" />
      ) : (
        <Popover open={open} onOpenChange={setOpen}>
          <ComboboxTrigger id={id} className="w-full min-w-0">
            <span className={cn('truncate', value === null && 'text-muted-foreground')}>
              {value?.title ?? __('Select a layout', 'yaycheckout')}
            </span>
            <ComboboxIcon />
          </ComboboxTrigger>
          <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
            <Command>
              <CommandInput placeholder={__('Search layouts', 'yaycheckout')} />
              <CommandList>
                <CommandEmpty>{__('No layouts found.', 'yaycheckout')}</CommandEmpty>
                <CommandGroup>
                  {layouts.map((layout) => (
                    <CommandItem
                      key={layout.id}
                      // Title first so cmdk's text filter matches what the user sees; id keeps duplicates distinct.
                      value={`${layout.title} #${layout.id}`}
                      onSelect={() => {
                        onChange(layout);
                        setOpen(false);
                      }}
                    >
                      <CheckIcon
                        data-selected={layout.id === value?.id}
                        className="data-[selected=true]:text-primary size-3.5 stroke-[2.5px] text-transparent"
                      />
                      <span className="truncate">{layout.title}</span>
                    </CommandItem>
                  ))}
                </CommandGroup>
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      )}
    </Field>
  );
}

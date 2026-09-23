import { useEffect, useId, useRef, useState } from 'react';
import { CheckIcon } from 'lucide-react';
import { useDebounceFn } from 'rooks';
import { __ } from '@wordpress/i18n';

import { useInfiniteLayoutsQuery } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES, type LayoutSummary } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { ComboboxIcon, ComboboxTrigger } from '@/components/ui/combobox';
import { Command, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Field, FieldLabel } from '@/components/ui/field';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';

/** Distance from the bottom of the list at which the next page is fetched. */
const LOAD_MORE_THRESHOLD_PX = 40;

interface LayoutSelectProps {
  value: LayoutSummary | null;
  onChange: (layout: LayoutSummary) => void;
}

/** Searchable layout picker for the Tools cards; picks the active layout once the list arrives. */
export function LayoutSelect({ value, onChange }: LayoutSelectProps) {
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  // useDebounceFn has no cancel; the trailing callback checks this ref to drop a stale value.
  const searchInputRef = useRef('');
  const [applySearch] = useDebounceFn((value: string) => {
    if (value !== searchInputRef.current) return;

    setSearch(value);
  }, 300);

  const list = useInfiniteLayoutsQuery({ status: LISTED_LAYOUT_STATUSES, search });
  const layouts = list.data?.pages.flatMap((page) => page.items) ?? [];
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
            {/* The server filters and paginates, so cmdk must show the rows it is given, in order. */}
            <Command shouldFilter={false}>
              <CommandInput
                placeholder={__('Search layouts', 'yaycheckout')}
                value={searchInput}
                onValueChange={(next) => {
                  searchInputRef.current = next;
                  setSearchInput(next);
                  applySearch(next);
                }}
              />
              <CommandList
                onScroll={(event) => {
                  const el = event.currentTarget;
                  if (!list.hasNextPage || list.isFetchingNextPage) return;
                  if (el.scrollHeight - el.scrollTop - el.clientHeight < LOAD_MORE_THRESHOLD_PX) list.fetchNextPage();
                }}
              >
                {layouts.length === 0 ? (
                  <p className="text-muted-foreground py-6 text-center text-sm">
                    {list.isFetching ? __('Searching…', 'yaycheckout') : __('No layouts found.', 'yaycheckout')}
                  </p>
                ) : (
                  <CommandGroup>
                    {layouts.map((layout) => (
                      <CommandItem
                        key={layout.id}
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
                )}
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      )}
    </Field>
  );
}

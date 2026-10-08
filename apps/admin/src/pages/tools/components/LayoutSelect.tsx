import { useId, useRef, useState } from 'react';
import { CheckIcon } from 'lucide-react';
import { useDebounceFn } from 'rooks';
import { __ } from '@wordpress/i18n';

import { loadMoreOnScroll } from '@/lib/helpers/infinite-list.helper';
import { useInfiniteLayoutsQuery } from '@/lib/queries/layout.queries';
import { LISTED_LAYOUT_STATUSES, type LayoutSummary } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { ComboboxIcon, ComboboxTrigger } from '@/components/ui/combobox';
import { Command, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Field, FieldLabel } from '@/components/ui/field';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { LayoutStatusBadge } from '@/pages/layouts/LayoutStatusBadge';

interface LayoutSelectProps {
  value: LayoutSummary | null;
  onChange: (layout: LayoutSummary) => void;
}

/**
 * Searchable layout picker for the Tools cards. Starts empty: the first page holds only the newest
 * layouts, so a default taken from it could be the wrong one.
 */
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
            <span className="ml-auto flex shrink-0 items-center gap-2">
              {value && <LayoutStatusBadge layout={value} className="shrink-0" />}
              <ComboboxIcon />
            </span>
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
                className={cn('transition-opacity', list.isPlaceholderData && 'opacity-60')}
                onScroll={(event) => loadMoreOnScroll(event, list)}
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
                        <LayoutStatusBadge layout={layout} className="ml-auto shrink-0" />
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

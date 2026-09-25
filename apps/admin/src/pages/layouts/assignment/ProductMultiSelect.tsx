import { useState } from 'react';
import { useDebouncedValue } from 'rooks';
import { __ } from '@wordpress/i18n';

import { useInfiniteProductsQuery } from '@/lib/queries/product.queries';
import { cn } from '@/lib/utils';
import { ComboboxCheckbox } from '@/components/ui/combobox';
import { Command, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { MultiSelectTrigger } from '@/pages/layouts/assignment/MultiSelectTrigger';

/** Distance from the bottom of the list at which the next page is fetched. */
const LOAD_MORE_THRESHOLD_PX = 40;

/** Searchable multi-select over published products, ten at a time as the list scrolls. Value is product ids. */
export function ProductMultiSelect({
  savedNames,
  value,
  onChange,
  className,
}: {
  /** Names of already-saved picks: they may never appear in a search page. */
  savedNames: Record<string, string>;
  value: string[];
  onChange: (value: string[]) => void;
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const [searchInput, setSearchInput] = useState('');
  const [search] = useDebouncedValue(searchInput, 300);

  // Names of picked products, kept across searches: a pick scrolled out of the current page still needs its badge.
  // Seeded with the saved picks' names from the rules reply; a deleted product has none and stays "#id".
  const [names, setNames] = useState<Record<string, string>>(savedNames);

  // Not fetched until the popover first opens: a closed picker costs no request.
  const list = useInfiniteProductsQuery(search, open);
  const products = list.data?.pages.flatMap((page) => page.items) ?? [];

  function toggle(id: string, name?: string) {
    if (name) setNames((prev) => ({ ...prev, [id]: name }));
    onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);
  }

  return (
    // Modal: inside the Sheet's scroll lock, a non-modal popover's list does not scroll with the wheel.
    <Popover modal open={open} onOpenChange={setOpen}>
      <MultiSelectTrigger
        className={className}
        placeholder={__('Select products', 'yaycheckout')}
        selected={value.map((id) => ({ value: id, label: names[id] ?? `#${id}`, missing: !(id in names) }))}
        onRemove={(id) => toggle(id)}
      />
      <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
        {/* The server filters and paginates, so cmdk must show the rows it is given, in order. */}
        <Command shouldFilter={false}>
          <CommandInput
            placeholder={__('Search products', 'yaycheckout')}
            value={searchInput}
            onValueChange={setSearchInput}
          />
          <CommandList
            className={cn('transition-opacity', list.isPlaceholderData && 'opacity-60')}
            onScroll={(event) => {
              const el = event.currentTarget;
              if (!list.hasNextPage || list.isFetchingNextPage) return;
              if (el.scrollHeight - el.scrollTop - el.clientHeight < LOAD_MORE_THRESHOLD_PX) list.fetchNextPage();
            }}
          >
            {products.length === 0 ? (
              <p className="text-muted-foreground py-6 text-center text-sm">
                {list.isFetching ? __('Searching…', 'yaycheckout') : __('No products found.', 'yaycheckout')}
              </p>
            ) : (
              <CommandGroup>
                {products.map((product) => {
                  const id = String(product.id);

                  return (
                    <CommandItem key={id} value={id} onSelect={() => toggle(id, product.name)}>
                      <ComboboxCheckbox selected={value.includes(id)} />
                      <span className="truncate">{product.name}</span>
                    </CommandItem>
                  );
                })}
                {list.isFetchingNextPage && (
                  <p className="text-muted-foreground py-2 text-center text-xs">{__('Loading…', 'yaycheckout')}</p>
                )}
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

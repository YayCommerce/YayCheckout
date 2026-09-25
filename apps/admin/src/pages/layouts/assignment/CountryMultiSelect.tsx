import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import {
  ComboboxBadge,
  ComboboxCheckbox,
  ComboboxIcon,
  ComboboxRemove,
  ComboboxTrigger,
} from '@/components/ui/combobox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent } from '@/components/ui/popover';

const MAX_BADGES = 3;

/** Case- and accent-folded, so "sao" finds São Tomé as Woo's own select does. */
function fold(text: string): string {
  return text
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase();
}

/** Searchable multi-select over Woo's country list. Value is ISO codes. */
export function CountryMultiSelect({
  value,
  onChange,
  className,
}: {
  value: string[];
  onChange: (value: string[]) => void;
  className?: string;
}) {
  const countries = window.yayCheckoutAdmin.countries;
  const [open, setOpen] = useState(false);

  function toggle(code: string) {
    onChange(value.includes(code) ? value.filter((c) => c !== code) : [...value, code]);
  }

  return (
    // Modal: inside the Sheet's scroll lock, a non-modal popover's list does not scroll with the wheel.
    <Popover modal open={open} onOpenChange={setOpen}>
      <ComboboxTrigger className={cn('min-w-0', className)}>
        <div className={cn('flex flex-wrap gap-1', value.length > 0 && '-ml-1')}>
          {value.length === 0 ? (
            <span className="text-muted-foreground">{__('Select countries', 'yaycheckout')}</span>
          ) : (
            <>
              {value.slice(0, MAX_BADGES).map((code) => (
                <ComboboxBadge key={code}>
                  {countries[code] ?? code}
                  <ComboboxRemove onRemove={() => toggle(code)} />
                </ComboboxBadge>
              ))}
              {value.length > MAX_BADGES && (
                <ComboboxBadge className="px-1">+{value.length - MAX_BADGES}</ComboboxBadge>
              )}
            </>
          )}
        </div>
        <ComboboxIcon />
      </ComboboxTrigger>
      <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
        {/* Substring, not cmdk's fuzzy score: "sao" should not rank Samoa first. */}
        <Command filter={(itemValue, search) => (fold(itemValue).includes(fold(search)) ? 1 : 0)}>
          <CommandInput placeholder={__('Search countries', 'yaycheckout')} />
          <CommandList>
            <CommandEmpty>{__('No countries found.', 'yaycheckout')}</CommandEmpty>
            <CommandGroup>
              {Object.entries(countries).map(([code, name]) => (
                // Name and code both searchable: "DE" finds Germany.
                <CommandItem key={code} value={`${name} ${code}`} onSelect={() => toggle(code)}>
                  <ComboboxCheckbox selected={value.includes(code)} />
                  {name}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

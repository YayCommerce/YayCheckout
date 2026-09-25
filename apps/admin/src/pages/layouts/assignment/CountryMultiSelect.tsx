import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { ComboboxCheckbox } from '@/components/ui/combobox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { MultiSelectTrigger } from '@/pages/layouts/assignment/MultiSelectTrigger';

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
      <MultiSelectTrigger
        className={className}
        placeholder={__('Select countries', 'yaycheckout')}
        selected={value.map((code) => ({ value: code, label: countries[code] ?? code }))}
        onRemove={toggle}
      />
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

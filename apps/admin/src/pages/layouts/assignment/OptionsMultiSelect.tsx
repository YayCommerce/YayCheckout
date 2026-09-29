import { useState } from 'react';

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

/** Searchable multi-select over a list known up front (countries, roles). Value is the option keys. */
export function OptionsMultiSelect({
  options,
  value,
  onChange,
  placeholder,
  searchPlaceholder,
  emptyText,
  className,
}: {
  /** Key => label, in display order. */
  options: Record<string, string>;
  value: string[];
  onChange: (value: string[]) => void;
  placeholder: string;
  searchPlaceholder: string;
  emptyText: string;
  className?: string;
}) {
  const [open, setOpen] = useState(false);

  function toggle(key: string) {
    onChange(value.includes(key) ? value.filter((picked) => picked !== key) : [...value, key]);
  }

  return (
    // Modal: inside the Sheet's scroll lock, a non-modal popover's list does not scroll with the wheel.
    <Popover modal open={open} onOpenChange={setOpen}>
      <MultiSelectTrigger
        className={className}
        placeholder={placeholder}
        selected={value.map((key) => ({
          value: key,
          label: options[key] ?? key,
          isMissing: !Object.hasOwn(options, key),
        }))}
        onRemove={toggle}
      />
      <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
        {/* Substring, not cmdk's fuzzy score: "sao" should not rank Samoa first. */}
        <Command filter={(itemValue, search) => (fold(itemValue).includes(fold(search)) ? 1 : 0)}>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            <CommandGroup>
              {Object.entries(options).map(([key, label]) => (
                // Label and key both searchable: "DE" finds Germany.
                <CommandItem key={key} value={`${label} ${key}`} onSelect={() => toggle(key)}>
                  <ComboboxCheckbox selected={value.includes(key)} />
                  {label}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

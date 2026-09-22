import { Plus, X } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import type { DesignPreset } from '@/lib/schema/design.schema';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Popover, PopoverClose, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ProTag } from '@/components/pro/ProGate';

/** Preset chips (apply / delete) plus "Save current as preset". Free sees the built-ins but cannot use them: the PRO tag says why. */
export function PresetsCard({
  presets,
  activePresetId,
  deleting,
  onApply,
  onDelete,
  onSaveCurrent,
}: {
  presets: DesignPreset[];
  activePresetId: string | null;
  deleting: boolean;
  onApply: (preset: DesignPreset) => void;
  onDelete: (id: string) => void;
  onSaveCurrent: () => void;
}) {
  return (
    <Card className={cn('gap-3 p-4', !isPro && 'opacity-60')}>
      <div className="flex items-center gap-2">
        <span className="flex-1 text-sm font-semibold">{__('Presets', 'yaycheckout')}</span>
        <ProTag />
      </div>
      <div className="flex flex-wrap gap-1.5">
        {presets
          .filter((preset) => isPro || preset.builtin)
          .map((preset) => (
            <span
              key={preset.id}
              className={cn(
                'transition-default inline-flex h-7 items-center rounded-full border text-xs',
                preset.id === activePresetId
                  ? 'border-primary bg-primary text-primary-foreground'
                  : 'bg-background hover:bg-accent',
              )}
            >
              <button
                type="button"
                onClick={() => onApply(preset)}
                disabled={!isPro}
                className={cn('cursor-pointer px-3 disabled:cursor-default', !preset.builtin && 'pe-1')}
              >
                {preset.name}
              </button>
              {!preset.builtin && (
                <Popover>
                  <PopoverTrigger asChild>
                    <button
                      type="button"
                      aria-label={__('Delete preset', 'yaycheckout')}
                      disabled={!isPro}
                      className="cursor-pointer pe-2 opacity-60 hover:opacity-100 disabled:cursor-default"
                    >
                      <X className="size-3" />
                    </button>
                  </PopoverTrigger>
                  <PopoverContent align="start" className="flex w-auto flex-col gap-3 p-3">
                    <p className="text-xs">
                      {sprintf(
                        /* translators: %s: preset name */
                        __('Delete preset "%s"?', 'yaycheckout'),
                        preset.name,
                      )}
                    </p>
                    <div className="flex justify-end gap-2">
                      <PopoverClose asChild>
                        <Button type="button" variant="outline" size="sm" className="h-7 px-2.5 text-xs">
                          {__('Cancel', 'yaycheckout')}
                        </Button>
                      </PopoverClose>
                      <PopoverClose asChild>
                        <Button
                          type="button"
                          variant="destructive"
                          size="sm"
                          className="h-7 px-2.5 text-xs"
                          onClick={() => onDelete(preset.id)}
                          disabled={deleting}
                        >
                          {__('Delete', 'yaycheckout')}
                        </Button>
                      </PopoverClose>
                    </div>
                  </PopoverContent>
                </Popover>
              )}
            </span>
          ))}
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="self-start"
        disabled={!isPro}
        onClick={onSaveCurrent}
      >
        <Plus />
        {__('Save current as preset', 'yaycheckout')}
      </Button>
    </Card>
  );
}

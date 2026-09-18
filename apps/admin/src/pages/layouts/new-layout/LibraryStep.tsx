import { useMemo, useState } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import type { NewLayoutFormValues } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { focusVariants } from '@/components/ui/variants/focus.variants';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { NameField } from '@/pages/layouts/new-layout/NameField';

const ALL_CATEGORIES = __('All', 'yaycheckout');

/** Template picker. Free sees the grid mostly locked; Pro the full grid. */
export function LibraryStep({
  onBack,
  onCancel,
  isPending,
  rootError,
}: {
  onBack: () => void;
  onCancel: () => void;
  isPending: boolean;
  rootError?: string;
}) {
  const { control, setValue } = useFormContext<NewLayoutFormValues>();
  const name = useWatch<NewLayoutFormValues, 'name'>({ control, name: 'name' });
  const selectedSlug = useWatch<NewLayoutFormValues, 'templateSlug'>({ control, name: 'templateSlug' });

  function selectTemplate(slug: string | null) {
    setValue('templateSlug', slug, { shouldValidate: true });
  }

  const { data, isPending: isLoadingTemplates } = useTemplateLibraryQuery();
  const [categoryFilter, setCategoryFilter] = useState<string>(ALL_CATEGORIES);

  const categories = useMemo(() => {
    if (!data) return [ALL_CATEGORIES];
    return [ALL_CATEGORIES, ...new Set(data.templates.map((t) => t.category))];
  }, [data]);

  const templates = useMemo(() => {
    if (!data) return [];
    return categoryFilter === ALL_CATEGORIES
      ? data.templates
      : data.templates.filter((t) => t.category === categoryFilter);
  }, [data, categoryFilter]);

  const unlockedTemplates = useMemo(() => data?.templates.filter((t) => !t.locked) ?? [], [data]);
  const lockedCount = data ? data.templates.length - unlockedTemplates.length : 0;

  // Clear a selection the new filter would hide.
  function onCategoryChange(category: string) {
    setCategoryFilter(category);

    const selected = data?.templates.find((t) => t.slug === selectedSlug);
    if (selected && category !== ALL_CATEGORIES && selected.category !== category) {
      selectTemplate(null);
    }
  }

  const disabled = '' === (name ?? '').trim() || !selectedSlug;

  return (
    <div className="flex flex-col gap-5">
      <NameField />

      <div className="flex flex-col gap-3 rounded-md border p-4">
        <div className="flex items-center justify-between gap-2">
          <span className="text-muted-foreground text-[10px] tracking-wide uppercase">
            {isPro
              ? __('Pro — full grid, filterable', 'yaycheckout')
              : __('Templates included in Lite', 'yaycheckout')}
          </span>
          {isPro && data && (
            <span className="text-muted-foreground text-xs">
              {data.templates.length}{' '}
              {_n('template', 'templates', data.templates.length, 'yaycheckout')}
            </span>
          )}
        </div>

        {isPro && (
          <div className="flex flex-wrap gap-2">
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                onClick={() => onCategoryChange(category)}
                className={cn(
                  'rounded-full border px-3 py-1 text-xs',
                  category === categoryFilter
                    ? 'bg-foreground text-background border-foreground'
                    : 'hover:border-muted-foreground',
                )}
              >
                {category}
              </button>
            ))}
          </div>
        )}

        {isLoadingTemplates ? (
          <div className="grid grid-cols-5 gap-3">
            {[0, 1, 2, 3, 4].map((i) => (
              <Skeleton key={i} className="h-20 w-full" />
            ))}
          </div>
        ) : isPro ? (
          <div className="grid h-84 scrollbar-none grid-cols-5 content-start gap-3 overflow-y-auto pt-0.5 pl-0.5">
            {templates.map((template) => (
              <button
                key={template.slug}
                type="button"
                disabled={template.locked}
                onClick={() => selectTemplate(template.slug)}
                title={template.locked ? __('Upgrade to Pro to use this template', 'yaycheckout') : undefined}
                className={cn(
                  'transition-default flex flex-col items-start gap-2 rounded-md border p-2 text-left',
                  focusVariants({ variant: 'none' }),
                  template.slug === selectedSlug && 'border-primary ring-primary ring-1',
                  template.locked ? 'cursor-not-allowed opacity-50' : 'hover:border-muted-foreground',
                )}
              >
                <div className="bg-muted h-16 w-full rounded-sm" />
                <div className="flex w-full items-center justify-between gap-1">
                  <span className="text-xs font-medium">{template.name}</span>
                  {template.locked && <ProTag />}
                </div>
              </button>
            ))}
          </div>
        ) : (
          <>
            <div className="grid grid-cols-3 gap-3">
              {unlockedTemplates.map((template) => (
                <button
                  key={template.slug}
                  type="button"
                  onClick={() => selectTemplate(template.slug)}
                  className={cn(
                    'transition-default hover:border-muted-foreground flex flex-col items-start gap-2 rounded-md border p-2 text-left',
                    focusVariants({ variant: 'none' }),
                    template.slug === selectedSlug && 'border-primary ring-primary ring-1',
                  )}
                >
                  <div className="bg-muted h-16 w-full rounded-sm" />
                  <span className="text-xs font-medium">{template.name}</span>
                </button>
              ))}
            </div>

            {lockedCount > 0 && (
              <div className="border-muted-foreground/40 bg-muted/40 flex flex-col gap-3 rounded-md border border-dashed p-3">
                <div className="flex items-center gap-2">
                  <ProTag />
                  <span className="text-muted-foreground flex-1 text-xs">
                    {lockedCount}{' '}
                    {_n('more template', 'more templates', lockedCount, 'yaycheckout')}
                  </span>
                  <UpgradeButton />
                </div>
                <div className="grid grid-cols-5 gap-2 opacity-45">
                  {Array.from({ length: 5 }, (_, i) => (
                    <div key={i} className="bg-muted border-muted-foreground/30 h-11 rounded-sm border" />
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {rootError && <p className="text-destructive text-sm">{rootError}</p>}

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-xs">
          {__('Your existing fields and gateways are mapped into the template you pick.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={onBack}>
            {__('Back', 'yaycheckout')}
          </Button>
          <Button type="button" variant="outline" onClick={onCancel}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button type="submit" disabled={disabled || isPending}>
            {isPending ? __('Creating…', 'yaycheckout') : __('Create draft', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </div>
  );
}

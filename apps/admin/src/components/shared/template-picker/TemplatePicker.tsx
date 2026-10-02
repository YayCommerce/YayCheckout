import { useMemo, useState } from 'react';
import { __, _n } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';
import { focusVariants } from '@/components/ui/variants/focus.variants';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { TemplateThumbnail } from './TemplateThumbnail';

const ALL_CATEGORIES = __('All', 'yaycheckout');

/**
 * Template grid. Free sees its unlocked templates plus a greyed strip of the
 * locked ones; Pro the full grid, filterable by category. A filter that hides
 * the selection clears it (onSelect(null)).
 */
export function TemplatePicker({
  selectedSlug,
  onSelect,
}: {
  selectedSlug: string | null | undefined;
  onSelect: (slug: string | null) => void;
}) {
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

  function onCategoryChange(category: string) {
    setCategoryFilter(category);

    const selected = data?.templates.find((t) => t.slug === selectedSlug);
    if (selected && category !== ALL_CATEGORIES && selected.category !== category) {
      onSelect(null);
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-md border p-4">
      <div className="flex items-center justify-between gap-2">
        <span className="text-muted-foreground text-[10px] tracking-wide uppercase">
          {isPro ? __('Pro — full grid, filterable', 'yaycheckout') : __('Templates included in Lite', 'yaycheckout')}
        </span>
        {isPro && data && (
          <span className="text-muted-foreground text-xs">
            {data.templates.length} {_n('template', 'templates', data.templates.length, 'yaycheckout')}
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
        <PickerSkeleton />
      ) : isPro ? (
        <div className="grid h-84 scrollbar-none grid-cols-5 content-start gap-3 overflow-y-auto pt-0.5 pl-0.5">
          {templates.map((template) => (
            <button
              key={template.slug}
              type="button"
              disabled={template.locked}
              onClick={() => onSelect(template.slug)}
              title={template.locked ? __('Upgrade to Pro to use this template', 'yaycheckout') : undefined}
              className={cn(
                'transition-default flex flex-col items-start gap-2 rounded-md border p-2 text-left',
                focusVariants({ variant: 'none' }),
                template.slug === selectedSlug && 'border-primary ring-primary ring-1',
                template.locked ? 'cursor-not-allowed opacity-50' : 'hover:border-muted-foreground',
              )}
            >
              <div className="aspect-3/2 w-full overflow-hidden rounded-sm border">
                <TemplateThumbnail slug={template.slug} />
              </div>
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
                onClick={() => onSelect(template.slug)}
                className={cn(
                  'transition-default hover:border-muted-foreground flex flex-col items-start gap-2 rounded-md border p-2 text-left',
                  focusVariants({ variant: 'none' }),
                  template.slug === selectedSlug && 'border-primary ring-primary ring-1',
                )}
              >
                <div className="aspect-3/2 w-full overflow-hidden rounded-sm border">
                  <TemplateThumbnail slug={template.slug} />
                </div>
                <span className="text-xs font-medium">{template.name}</span>
              </button>
            ))}
          </div>

          {lockedCount > 0 && (
            <div className="border-muted-foreground/40 bg-muted/40 flex flex-col gap-3 rounded-md border border-dashed p-3">
              <div className="flex items-center gap-2">
                <ProTag />
                <span className="text-muted-foreground flex-1 text-xs">
                  {lockedCount} {_n('more template', 'more templates', lockedCount, 'yaycheckout')}
                </span>
                <UpgradeButton />
              </div>
              <div className="grid grid-cols-4 gap-2 opacity-45">
                {data?.templates
                  .filter((template) => template.locked)
                  .map((template) => (
                    <div key={template.slug} className="aspect-3/2 overflow-hidden rounded-sm border">
                      <TemplateThumbnail slug={template.slug} />
                    </div>
                  ))}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

/** Same boxes as the loaded grid (Pro: fixed-height 5-col; Free: 3 cards + locked strip) so the dialog doesn't grow when templates arrive. */
function PickerSkeleton() {
  const card = (i: number) => (
    <div key={i} className="flex flex-col gap-2 rounded-md border p-2">
      <Skeleton className="aspect-3/2 w-full" />
      <Skeleton className="h-4 w-2/3" />
    </div>
  );

  if (isPro) {
    return (
      <div className="grid h-84 grid-cols-5 content-start gap-3 overflow-hidden">{[...Array(10).keys()].map(card)}</div>
    );
  }

  return (
    <>
      <div className="grid grid-cols-3 gap-3">{[0, 1, 2].map(card)}</div>
      <div className="flex flex-col gap-3 rounded-md border border-dashed p-3">
        <Skeleton className="h-8 w-full" />
        <div className="grid grid-cols-4 gap-2">
          {[...Array(8).keys()].map((i) => (
            <Skeleton key={i} className="aspect-3/2 w-full" />
          ))}
        </div>
      </div>
    </>
  );
}

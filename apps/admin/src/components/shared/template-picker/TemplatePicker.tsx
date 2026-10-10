import { useMemo, useState } from 'react';
import { __, _n } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { isPickable, useTemplate, useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import type { Template } from '@/lib/schema/template.schema';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { focusVariants } from '@/components/ui/variants/focus.variants';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { TemplateThumbnail } from './TemplateThumbnail';

const ALL_CATEGORIES = __('All', 'yaycheckout');

type GridProps = {
  templates: Template[];
  isLoading: boolean;
  selectedSlug: string | null | undefined;
  onSelect: (slug: string | null) => void;
};

/** A template without its files yet: shown, never selectable. Locked wins, so a locked one keeps its Pro tag. */
function isComingSoon(template: Template): boolean {
  return !template.locked && !template.available;
}

function tileTitle(template: Template): string | undefined {
  if (template.locked) return __('Upgrade to Pro to use this template', 'yaycheckout');
  if (isComingSoon(template)) return COMING_SOON_TITLE;
  return undefined;
}

/** Sits on the thumbnail's corner, so the name keeps the tile's width. */
function ComingSoonBadge() {
  return (
    <Badge variant="dimmed-outline" className="bg-background absolute top-1 right-1">
      {__('Coming soon', 'yaycheckout')}
    </Badge>
  );
}

const COMING_SOON_TITLE = __('This template is coming soon', 'yaycheckout');

/**
 * Template grid. Free sees its unlocked templates plus a greyed strip of the
 * locked ones; Pro the full grid, filterable by category. Coming soon templates
 * show in place but can't be picked. A filter that hides the selection clears
 * it (onSelect(null)).
 */
export function TemplatePicker({
  selectedSlug,
  onSelect,
}: {
  selectedSlug: string | null | undefined;
  onSelect: (slug: string | null) => void;
}) {
  const { data, isPending: isLoadingTemplates } = useTemplateLibraryQuery();
  const gridProps = { templates: data?.templates ?? [], isLoading: isLoadingTemplates, selectedSlug, onSelect };

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

      {isPro ? <ProTemplateGrid {...gridProps} /> : <FreeTemplateGrid {...gridProps} />}
    </div>
  );
}

function ProTemplateGrid({ templates, isLoading, selectedSlug, onSelect }: GridProps) {
  const [categoryFilter, setCategoryFilter] = useState<string>(ALL_CATEGORIES);
  const selected = useTemplate(selectedSlug);

  const categories = useMemo(() => [ALL_CATEGORIES, ...new Set(templates.map((t) => t.category))], [templates]);

  const shownTemplates = useMemo(
    () => (categoryFilter === ALL_CATEGORIES ? templates : templates.filter((t) => t.category === categoryFilter)),
    [templates, categoryFilter],
  );

  function onCategoryChange(category: string) {
    setCategoryFilter(category);

    if (selected && category !== ALL_CATEGORIES && selected.category !== category) {
      onSelect(null);
    }
  }

  return (
    <>
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

      {isLoading ? (
        <PickerSkeleton />
      ) : (
        <div className="grid h-84 scrollbar-none grid-cols-5 content-start gap-3 overflow-y-auto pt-0.5 pl-0.5">
          {shownTemplates.map((template) => (
            <button
              key={template.slug}
              type="button"
              disabled={!isPickable(template)}
              onClick={() => onSelect(template.slug)}
              title={tileTitle(template)}
              className={cn(
                'transition-default flex flex-col items-start gap-2 rounded-md border p-2 text-left',
                focusVariants({ variant: 'none' }),
                template.slug === selectedSlug && 'border-primary ring-primary ring-1',
                isPickable(template) ? 'hover:border-muted-foreground' : 'cursor-not-allowed opacity-50',
              )}
            >
              <div className="relative aspect-3/2 w-full overflow-hidden rounded-sm border">
                <TemplateThumbnail slug={template.slug} />
                {isComingSoon(template) && <ComingSoonBadge />}
              </div>
              <div className="flex w-full items-center justify-between gap-1">
                <span className="text-xs font-medium">{template.name}</span>
                {template.locked && <ProTag />}
              </div>
            </button>
          ))}
        </div>
      )}
    </>
  );
}

function FreeTemplateGrid({ templates, isLoading, selectedSlug, onSelect }: GridProps) {
  if (isLoading) {
    return <PickerSkeleton />;
  }

  const unlockedTemplates = templates.filter((t) => !t.locked);
  const lockedTemplates = templates.filter((t) => t.locked);

  return (
    <>
      <div className="grid grid-cols-3 gap-3">
        {unlockedTemplates.map((template) => (
          <button
            key={template.slug}
            type="button"
            disabled={!isPickable(template)}
            onClick={() => onSelect(template.slug)}
            title={tileTitle(template)}
            className={cn(
              'transition-default flex flex-col items-start gap-2 rounded-md border p-2 text-left',
              focusVariants({ variant: 'none' }),
              template.slug === selectedSlug && 'border-primary ring-primary ring-1',
              isPickable(template) ? 'hover:border-muted-foreground' : 'cursor-not-allowed opacity-50',
            )}
          >
            <div className="relative aspect-3/2 w-full overflow-hidden rounded-sm border">
              <TemplateThumbnail slug={template.slug} />
              {isComingSoon(template) && <ComingSoonBadge />}
            </div>
            <span className="text-xs font-medium">{template.name}</span>
          </button>
        ))}
      </div>

      {lockedTemplates.length > 0 && (
        <div className="border-muted-foreground/40 bg-muted/40 flex flex-col gap-3 rounded-md border border-dashed p-3">
          <div className="flex items-center gap-2">
            <ProTag />
            <span className="text-muted-foreground flex-1 text-xs">
              {lockedTemplates.length} {_n('more template', 'more templates', lockedTemplates.length, 'yaycheckout')}
            </span>
            <UpgradeButton />
          </div>
          <div className="grid grid-cols-4 gap-2 opacity-45">
            {lockedTemplates.map((template) => (
              <div key={template.slug} className="aspect-3/2 overflow-hidden rounded-sm border">
                <TemplateThumbnail slug={template.slug} />
              </div>
            ))}
          </div>
        </div>
      )}
    </>
  );
}

/**
 * Matches the loaded grid's boxes, so the dialog doesn't grow when templates arrive.
 * Free's card counts are a guess.
 */
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

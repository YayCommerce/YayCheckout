import { useMemo, useState, type ReactNode } from 'react';
import { Clock, Lock } from 'lucide-react';
import { __, _n } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { isPickable, useTemplate, useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import type { Template } from '@/lib/schema/template.schema';
import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';
import { focusVariants } from '@/components/ui/variants/focus.variants';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { FRAME_ASPECT, TemplatePreview } from './TemplatePreview';

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

/**
 * Veils a card that can't be picked and names why, centred on the preview. It lightens on hover so the
 * page still reads while it scrolls.
 */
function UnavailableOverlay({ template }: { template: Template }) {
  const Icon = template.locked ? Lock : Clock;

  return (
    <div className="bg-background/60 group-hover:bg-background/25 group-focus-visible:bg-background/25 pointer-events-none absolute inset-0 flex items-center justify-center backdrop-blur-[1px] transition-colors duration-300 group-hover:backdrop-blur-none">
      <span className="bg-foreground text-background inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium shadow-sm">
        <Icon className="size-3.5" aria-hidden="true" />
        {template.locked ? __('Pro', 'yaycheckout') : __('Coming soon', 'yaycheckout')}
      </span>
    </div>
  );
}

const COMING_SOON_TITLE = __('This template is coming soon', 'yaycheckout');

/**
 * Template cards, each previewing its mock page. Free sees its unlocked templates,
 * then the locked ones under an upgrade row; Pro the full grid, filterable by
 * category. Locked and Coming soon cards preview in full but can't be picked. A
 * filter that hides the selection clears it (onSelect(null)).
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
        <ScrollArea>
          <TemplateCards templates={shownTemplates} selectedSlug={selectedSlug} onSelect={onSelect} />
        </ScrollArea>
      )}
    </>
  );
}

/** The scroll box every grid sits in, so the dialog's name field and footer stay in view. */
function ScrollArea({ children }: { children: ReactNode }) {
  return <div className="flex max-h-[60vh] flex-col gap-4 overflow-y-auto p-0.5">{children}</div>;
}

function CardGrid({ children }: { children: ReactNode }) {
  return <div className="grid grid-cols-3 content-start gap-4">{children}</div>;
}

function TemplateCards({ templates, selectedSlug, onSelect }: Omit<GridProps, 'isLoading'>) {
  return (
    <CardGrid>
      {templates.map((template) => (
        <TemplateTile
          key={template.slug}
          template={template}
          isSelected={template.slug === selectedSlug}
          onSelect={onSelect}
        />
      ))}
    </CardGrid>
  );
}

/** One template card. Locked and Coming soon cards stay at full opacity, since the preview is the pitch. */
function TemplateTile({
  template,
  isSelected,
  onSelect,
}: {
  template: Template;
  isSelected: boolean;
  onSelect: (slug: string) => void;
}) {
  return (
    <button
      type="button"
      disabled={!isPickable(template)}
      onClick={() => onSelect(template.slug)}
      title={tileTitle(template)}
      className={cn(
        'group transition-default flex flex-col items-start gap-2 rounded-md border p-2 text-left',
        focusVariants({ variant: 'none' }),
        isSelected && 'border-primary ring-primary ring-1',
        isPickable(template) ? 'hover:border-muted-foreground' : 'cursor-not-allowed',
      )}
    >
      <TemplatePreview template={template}>
        {!isPickable(template) && <UnavailableOverlay template={template} />}
      </TemplatePreview>
      <div className="flex w-full items-center justify-between gap-1">
        <span className="text-xs font-medium">{template.name}</span>
        {template.locked && <ProTag />}
      </div>
    </button>
  );
}

function FreeTemplateGrid({ templates, isLoading, selectedSlug, onSelect }: GridProps) {
  if (isLoading) {
    return <PickerSkeleton />;
  }

  const unlockedTemplates = templates.filter((t) => !t.locked);
  const lockedTemplates = templates.filter((t) => t.locked);

  return (
    <ScrollArea>
      <TemplateCards templates={unlockedTemplates} selectedSlug={selectedSlug} onSelect={onSelect} />

      {lockedTemplates.length > 0 && (
        <>
          <div className="flex items-center gap-2">
            <ProTag />
            <span className="text-muted-foreground flex-1 text-xs">
              {lockedTemplates.length} {_n('more template', 'more templates', lockedTemplates.length, 'yaycheckout')}
            </span>
            <UpgradeButton />
          </div>
          <TemplateCards templates={lockedTemplates} selectedSlug={selectedSlug} onSelect={onSelect} />
        </>
      )}
    </ScrollArea>
  );
}

/** Matches the loaded cards' boxes (FRAME_ASPECT), so the dialog doesn't grow when templates arrive. */
function PickerSkeleton() {
  return (
    <CardGrid>
      {[...Array(6).keys()].map((i) => (
        <div key={i} className="flex flex-col gap-2 rounded-md border p-2">
          <Skeleton className="w-full" style={{ aspectRatio: FRAME_ASPECT }} />
          <Skeleton className="h-4 w-2/3" />
        </div>
      ))}
    </CardGrid>
  );
}

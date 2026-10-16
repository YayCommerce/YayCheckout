import { useMemo, useState, type ReactNode } from 'react';
import { Check, Clock } from 'lucide-react';
import { __, _n } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { isLocked, isPickable, useTemplate, useTemplates } from '@/lib/queries/template.queries';
import type { Template } from '@/lib/schema/template.schema';
import { cn } from '@/lib/utils';
import { focusVariants } from '@/components/ui/variants/focus.variants';
import { ProBadge, UpgradeButton } from '@/components/pro/ProGate';
import { TemplatePreview } from './TemplatePreview';

const ALL_CATEGORIES = __('All', 'yaycheckout');

type GridProps = {
  templates: Template[];
  selectedSlug: string | null | undefined;
  onSelect: (slug: string | null) => void;
};

/**
 * Veils a Coming soon card, one without its files yet, centred on the preview. It lightens on hover so the
 * page still reads while it scrolls.
 */
function ComingSoonOverlay() {
  return (
    <div className="bg-background/60 group-hover:bg-background/25 group-focus-visible:bg-background/25 pointer-events-none absolute inset-0 flex items-center justify-center backdrop-blur-[1px] transition-colors duration-300 group-hover:backdrop-blur-none">
      <span className="bg-foreground text-background inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium shadow-sm">
        <Clock className="size-3.5" aria-hidden="true" />
        {__('Coming soon', 'yaycheckout')}
      </span>
    </div>
  );
}

const COMING_SOON_TITLE = __('This template is coming soon', 'yaycheckout');

const LOCKED_TITLE = __('Upgrade to Pro to use this template', 'yaycheckout');

/** Veils a locked card like a Coming soon one; on hover it lightens and shows the Upgrade button. */
function LockedOverlay() {
  return (
    <div className="bg-background/60 group-hover:bg-background/25 group-focus-visible:bg-background/25 absolute inset-0 flex items-center justify-center backdrop-blur-[1px] transition-colors duration-300 group-hover:backdrop-blur-none">
      <UpgradeButton className="transition-default opacity-0 group-focus-within:opacity-100 group-hover:opacity-100" />
    </div>
  );
}

/**
 * Template cards, each previewing its mock page. Pro gets the full grid, filterable by category. Free
 * gets the same templates, with the ones built from Pro blocks locked behind a Pro badge. Coming soon
 * and locked cards preview in full but can't be picked. A filter that hides the selection clears it (onSelect(null)).
 */
export function TemplatePicker({
  selectedSlug,
  onSelect,
}: {
  selectedSlug: string | null | undefined;
  onSelect: (slug: string | null) => void;
}) {
  const templates = useTemplates();
  const gridProps = { templates, selectedSlug, onSelect };

  return (
    <div className="flex flex-col gap-3 rounded-md border p-4">
      <div className="flex items-center justify-between gap-2">
        <span className="text-muted-foreground text-[10px] tracking-wide uppercase">
          {isPro ? __('Pro — full grid, filterable', 'yaycheckout') : __('Templates', 'yaycheckout')}
        </span>
        {isPro && (
          <span className="text-muted-foreground text-xs">
            {templates.length} {_n('template', 'templates', templates.length, 'yaycheckout')}
          </span>
        )}
      </div>

      {isPro ? (
        <ProTemplateGrid {...gridProps} />
      ) : (
        <ScrollArea>
          <TemplateCards {...gridProps} />
        </ScrollArea>
      )}
    </div>
  );
}

function ProTemplateGrid({ templates, selectedSlug, onSelect }: GridProps) {
  const [categoryFilter, setCategoryFilter] = useState<string>(ALL_CATEGORIES);
  const selected = useTemplate(selectedSlug);

  const categories = useMemo(() => [ALL_CATEGORIES, ...new Set(templates.flatMap((t) => t.categories))], [templates]);

  const shownTemplates = useMemo(
    () =>
      categoryFilter === ALL_CATEGORIES ? templates : templates.filter((t) => t.categories.includes(categoryFilter)),
    [templates, categoryFilter],
  );

  function onCategoryChange(category: string) {
    setCategoryFilter(category);

    if (selected && category !== ALL_CATEGORIES && !selected.categories.includes(category)) {
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

      <ScrollArea>
        <TemplateCards templates={shownTemplates} selectedSlug={selectedSlug} onSelect={onSelect} />
      </ScrollArea>
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

/**
 * Built Free templates first, then Pro ones (built, or locked on Free), then Coming soon, each in
 * catalogue order.
 */
function byAvailableFirst(templates: Template[]): Template[] {
  const rank = (t: Template) => (!t.available && !isLocked(t) ? 2 : t.pro ? 1 : 0);
  return [...templates].sort((a, b) => rank(a) - rank(b));
}

function TemplateCards({ templates, selectedSlug, onSelect }: GridProps) {
  return (
    <CardGrid>
      {byAvailableFirst(templates).map((template) => (
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

/** The selected card's corner mark, the check most pickers use. */
function SelectedCheck() {
  return (
    <span className="bg-primary text-primary-foreground ring-background absolute top-2 right-2 flex size-6 items-center justify-center rounded-full shadow-md ring-2">
      <Check className="size-3.5" strokeWidth={3} aria-hidden="true" />
    </span>
  );
}

/** One template card. Coming soon and locked cards still preview, since the preview is the pitch. */
function TemplateTile({
  template,
  isSelected,
  onSelect,
}: {
  template: Template;
  isSelected: boolean;
  onSelect: (slug: string) => void;
}) {
  if (isLocked(template)) {
    return <LockedTemplateTile template={template} />;
  }

  const pickable = isPickable(template);

  return (
    <button
      type="button"
      disabled={!pickable}
      onClick={() => onSelect(template.slug)}
      title={pickable ? undefined : COMING_SOON_TITLE}
      aria-pressed={isSelected}
      className={cn(
        'group transition-default flex flex-col items-start gap-2 rounded-md border p-2 text-left',
        focusVariants({ variant: 'none' }),
        isSelected ? 'border-primary ring-primary bg-primary/5 ring-2' : pickable && 'hover:border-muted-foreground',
        !pickable && 'cursor-not-allowed',
      )}
    >
      <TemplatePreview template={template}>
        {!pickable && <ComingSoonOverlay />}
        {isSelected && <SelectedCheck />}
      </TemplatePreview>
      <span className={cn('text-xs font-medium', isSelected && 'text-primary')}>{template.name}</span>
    </button>
  );
}

/**
 * A Pro template on Free: its preview with a Pro badge, and an Upgrade button on hover. A div, not the
 * tile's button, since it holds the Upgrade button; it is focusable so the preview still scrolls by keyboard.
 */
function LockedTemplateTile({ template }: { template: Template }) {
  return (
    <div
      role="group"
      tabIndex={0}
      title={LOCKED_TITLE}
      aria-label={template.name}
      className={cn(
        'group transition-default hover:border-muted-foreground flex flex-col items-start gap-2 rounded-md border p-2 text-left',
        focusVariants({ variant: 'none' }),
      )}
    >
      <TemplatePreview template={template}>
        <LockedOverlay />
        <ProBadge className="absolute top-2 right-2 shadow-sm" />
      </TemplatePreview>
      <span className="text-xs font-medium">{template.name}</span>
    </div>
  );
}

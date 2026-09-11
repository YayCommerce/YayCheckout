import { useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { focusVariants } from '@/components/ui/variants/focus.variants';
import { NameField } from '@/pages/layouts/new-layout/NameField';
import type { FormError } from '@/pages/layouts/new-layout/NewLayoutModal';

const ALL_CATEGORIES = __('All', 'yaycheckout');

/** yc02-10 (Free, mostly locked) / yc02-10b (Pro, full grid + filter chips):
 * category chips are derived from the fetched template list, not hardcoded. */
export function LibraryStep({
  name,
  onNameChange,
  selectedSlug,
  onSelectSlug,
  onBack,
  onCancel,
  onSubmit,
  onUseSuggestedName,
  isPending,
  error,
}: {
  name: string;
  onNameChange: (value: string) => void;
  selectedSlug: string | null;
  onSelectSlug: (slug: string | null) => void;
  onBack: () => void;
  onCancel: () => void;
  onSubmit: () => void;
  onUseSuggestedName: (suggestedName: string) => void;
  isPending: boolean;
  error: FormError | null;
}) {
  const plan = isPro ? 'pro' : 'free';
  const { data, isPending: isLoadingTemplates } = useTemplateLibraryQuery(plan);
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

  useEffect(() => {
    if (!selectedSlug && data) {
      const firstUnlocked = data.templates.find((t) => !t.locked);
      if (firstUnlocked) onSelectSlug(firstUnlocked.slug);
    }
  }, [data]);

  useEffect(() => {
    if (data && selectedSlug && !templates.some((t) => t.slug === selectedSlug)) {
      onSelectSlug(null);
    }
  }, [data, templates, selectedSlug, onSelectSlug]);

  const disabled = '' === name.trim() || !selectedSlug || error?.kind === 'duplicate';

  return (
    <div className="flex flex-col gap-5">
      <NameField name={name} onNameChange={onNameChange} error={error} onUseSuggestedName={onUseSuggestedName} />

      <div className="flex flex-col gap-3 rounded-md border p-4">
        <div className="flex items-center justify-between gap-2">
          <span className="text-muted-foreground text-[10px] tracking-wide uppercase">
            {plan === 'pro'
              ? __('Pro — full grid, filterable', 'yaycheckout')
              : __('Templates included in Lite', 'yaycheckout')}
          </span>
          {plan === 'pro' && data && (
            <span className="text-muted-foreground text-xs">
              {data.templates.length} {__('templates', 'yaycheckout')}
            </span>
          )}
        </div>

        {plan === 'pro' && (
          <div className="flex flex-wrap gap-2">
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                onClick={() => setCategoryFilter(category)}
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
        ) : plan === 'pro' ? (
          <div className="grid h-84 scrollbar-none grid-cols-5 content-start gap-3 overflow-y-auto pt-0.5 pl-0.5">
            {templates.map((template) => (
              <button
                key={template.slug}
                type="button"
                disabled={template.locked}
                onClick={() => onSelectSlug(template.slug)}
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
                  {template.locked && (
                    <Badge variant="outline" className="px-1.5 text-[10px]">
                      {__('PRO', 'yaycheckout')}
                    </Badge>
                  )}
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
                  onClick={() => onSelectSlug(template.slug)}
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
                  <Badge variant="outline" className="px-1.5 text-[10px]">
                    {__('PRO', 'yaycheckout')}
                  </Badge>
                  <span className="text-muted-foreground flex-1 text-xs">
                    {lockedCount} {__('more templates', 'yaycheckout')}
                  </span>
                  <Button size="sm" variant="outline">
                    {__('Upgrade', 'yaycheckout')}
                  </Button>
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

      {error?.kind === 'generic' && <p className="text-destructive text-sm">{error.message}</p>}

      <DialogFooter className="items-center gap-3 border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-xs">
          {__('Your existing fields and gateways are mapped into the template you pick.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onBack}>
            {__('Back', 'yaycheckout')}
          </Button>
          <Button variant="outline" onClick={onCancel}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button onClick={onSubmit} disabled={disabled || isPending}>
            {isPending ? __('Creating…', 'yaycheckout') : __('Create draft', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </div>
  );
}

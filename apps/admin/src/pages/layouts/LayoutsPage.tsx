import { useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import {
  LAYOUT_KEYS,
  useLayoutCountsQuery,
  useLayoutsQuery,
  usePublishLayoutMutation,
} from '@/lib/queries/layout.queries';
import type { LayoutCounts, LayoutListResponse, LayoutSummary } from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { NewLayoutModal } from '@/pages/layouts/new-layout/NewLayoutModal';
import PageLayout from '@/pages/PageLayout';

type View = 'listed' | 'archived';

/*
 * GET /layouts (items) + GET /layouts/counts (tab totals) from
 * LayoutRepository — two separate queries so counts has one cache key
 * instead of diverging per status filter. Edit and Publish are real; Search,
 * Health/Compatibility, Preview, Set active, "⋯", Restore and Delete for good
 * render with the demo's exact DOM state (enabled/disabled/hidden, labels)
 * but no onClick — each still lacks a backend action. See LayoutRowActions.
 */
export default function LayoutsPage() {
  const [view, setView] = useState<View>('listed');
  const list = useLayoutsQuery({
    status: view === 'archived' ? ['archived'] : ['draft', 'published'],
  });
  const countsQuery = useLayoutCountsQuery();

  const isPending = list.isPending || countsQuery.isPending;
  const error = list.error ?? countsQuery.error;

  return (
    <PageLayout
      heading={__('Layouts', 'yaycheckout')}
      description={__('Create, publish and activate checkout layouts.', 'yaycheckout')}
    >
      {/* isPending only fires once, before either query has ever loaded —
       * `list`'s placeholderData: keepPreviousData flips isFetching instead
       * on later tab switches, and `counts` has no status filter to switch
       * at all, so neither re-fires isPending after the first load. */}
      {isPending && <LayoutsPageSkeleton />}
      {error && <p className="text-destructive text-sm">{error.message}</p>}
      {list.data && countsQuery.data && (
        <LayoutsPageBody
          data={list.data}
          counts={countsQuery.data}
          countsFetching={countsQuery.isFetching}
          isFetching={list.isFetching}
          isPlaceholderData={list.isPlaceholderData}
          view={view}
          onViewChange={setView}
        />
      )}
    </PageLayout>
  );
}

function LayoutsPageBody({
  data,
  counts,
  countsFetching,
  isFetching,
  isPlaceholderData,
  view,
  onViewChange,
}: {
  data: LayoutListResponse;
  counts: LayoutCounts;
  countsFetching: boolean;
  isFetching: boolean;
  isPlaceholderData: boolean;
  view: View;
  onViewChange: (view: View) => void;
}) {
  const queryClient = useQueryClient();
  const [newLayoutOpen, setNewLayoutOpen] = useState(false);
  const [editingLayoutId, setEditingLayoutId] = useState<number | null>(null);

  const editorUrl = useMemo(() => {
    if (!editingLayoutId) {
      return '';
    }

    const url = new URL('post.php', window.yayCheckoutMeta.wpMeta.adminUrl);
    url.searchParams.set('post', String(editingLayoutId));
    url.searchParams.set('action', 'edit');
    url.searchParams.set('yc-embed', '1');
    return url.toString();
  }, [editingLayoutId]);

  const onEditorOpenChange = (open: boolean) => {
    if (!open) {
      setEditingLayoutId(null);
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.all });
    }
  };

  // Tab switch (isPlaceholderData) disables both buttons; a same-tab
  // refetch (e.g. after creating a draft) doesn't.
  const tabsDisabled = isFetching && isPlaceholderData;

  return (
    <div className="space-y-4">
      <NewLayoutModal open={newLayoutOpen} onOpenChange={setNewLayoutOpen} />

      <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <h2 className="flex-1 text-lg font-semibold">{__('Your layouts', 'yaycheckout')}</h2>
            {/* No search arg on GET /layouts yet — visible once the list is
             * long enough to need it, but not wired to a query. Sourced from
             * counts, not data.items.length, so this row doesn't depend on
             * the list query at all. */}
            {(view === 'archived' ? counts.archived : counts.listed) >= 10 && (
              <Input placeholder={__('Search layouts…', 'yaycheckout')} className="w-52" />
            )}
            <Button size="sm" onClick={() => setNewLayoutOpen(true)}>
              {__('Add layout', 'yaycheckout')}
            </Button>
          </div>

          <div className="flex">
            <Button
              size="sm"
              variant={view === 'listed' ? 'primary' : 'outline'}
              className="rounded-r-none"
              disabled={tabsDisabled}
              onClick={() => onViewChange('listed')}
            >
              {__('Active list', 'yaycheckout')} (<CountValue loading={countsFetching} value={counts.listed} />)
            </Button>
            <Button
              size="sm"
              variant={view === 'archived' ? 'primary' : 'outline'}
              className="rounded-l-none"
              disabled={tabsDisabled}
              onClick={() => onViewChange('archived')}
            >
              {__('Archive', 'yaycheckout')} (<CountValue loading={countsFetching} value={counts.archived} />)
            </Button>
          </div>

          {isPlaceholderData ? (
            <TableRowsSkeleton />
          ) : data.items.length === 0 ? (
            <Card>
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon" />
                  <EmptyTitle>
                    {view === 'archived'
                      ? __('Nothing archived', 'yaycheckout')
                      : __('Nothing here yet', 'yaycheckout')}
                  </EmptyTitle>
                  <EmptyDescription>
                    {view === 'archived'
                      ? __('Layouts you archive will show up here.', 'yaycheckout')
                      : counts.archived > 0
                        ? __(
                            'All of your layouts are archived. Restore one to bring it back to this list, or start a new draft. Archiving never deletes anything.',
                            'yaycheckout',
                          )
                        : __('Create your first checkout layout to get started.', 'yaycheckout')}
                  </EmptyDescription>
                </EmptyHeader>
                {view === 'listed' && (
                  <EmptyContent>
                    <Button size="sm" onClick={() => setNewLayoutOpen(true)}>
                      {__('Add layout', 'yaycheckout')}
                    </Button>
                    {counts.archived > 0 && (
                      <Button size="sm" variant="outline" onClick={() => onViewChange('archived')}>
                        {__('Restore from archive', 'yaycheckout')}
                      </Button>
                    )}
                  </EmptyContent>
                )}
              </Empty>
            </Card>
          ) : (
            <Card className={cn('transition-opacity', isFetching && 'opacity-60')}>
              <LayoutsTable items={data.items} view={view} onEditLayout={setEditingLayoutId} />
              {/* No page/per_page args on GET /layouts yet — the list is
               * already fetched whole, so page buttons are visual only. */}
              {data.items.length >= 10 && <LayoutsPagination itemCount={data.items.length} />}
            </Card>
          )}
        </div>

        <aside className="space-y-4">
          {/* No HealthChecker yet — content is a placeholder, not fabricated. */}
          <Card>
            <CardHeader>
              <CardTitle>{__('Health', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <p className="text-muted-foreground text-sm">{__('Not available yet.', 'yaycheckout')}</p>
          </Card>

          {/* No compatibility scan yet — same treatment as Health. */}
          <Card>
            <CardHeader>
              <CardTitle>{__('Compatibility', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <p className="text-muted-foreground text-sm">{__('Not available yet.', 'yaycheckout')}</p>
          </Card>
        </aside>
      </div>

      <Dialog open={null !== editingLayoutId} onOpenChange={onEditorOpenChange}>
        <DialogContent className="!flex h-[92vh] w-[96vw] max-w-[96vw] flex-col gap-0 overflow-hidden p-0 sm:max-w-[96vw]">
          <DialogHeader className="shrink-0 gap-1 border-b px-4 py-2">
            <DialogTitle className="text-base">{__('Edit layout', 'yaycheckout')}</DialogTitle>
          </DialogHeader>

          {editorUrl && (
            <div className="min-h-0 flex-1 overflow-hidden">
              <iframe
                title={__('Layout editor', 'yaycheckout')}
                src={editorUrl}
                className="block h-full w-full border-0"
              />
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}

/**
 * Shows a skeleton while the counts query is refetching (create draft, close
 * editor, stale-on-focus) so a slow site never displays a stale number as if
 * it were current. The skeleton's fade-in is delayed in CSS: a refetch that
 * finishes within that window unmounts it before it ever becomes visible, so
 * fast sites don't flicker — no timers or state, just `animation-delay`.
 */
function CountValue({ loading, value }: { loading: boolean; value: number }) {
  if (!loading) {
    return <>{value}</>;
  }

  return (
    <span className="animate-in fade-in fill-mode-backwards inline-block align-middle [animation-delay:150ms]">
      <Skeleton className="size-3" />
    </span>
  );
}

const PAGE_SIZE = 10;

/** Visual only — see the "No page/per_page args" note above its call site. */
function LayoutsPagination({ itemCount }: { itemCount: number }) {
  const totalPages = Math.ceil(itemCount / PAGE_SIZE);

  return (
    <div className="mt-3 flex items-center gap-4 border-t pt-3">
      <div className="flex">
        {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
          <Button
            key={page}
            size="sm"
            variant={page === 1 ? 'primary' : 'outline'}
            className="h-7 rounded-none px-2.5 text-xs first:rounded-l-md last:rounded-r-md"
          >
            {page}
          </Button>
        ))}
      </div>
    </div>
  );
}

function LayoutsTable({
  items,
  view,
  onEditLayout,
}: {
  items: LayoutSummary[];
  view: View;
  onEditLayout: (id: number) => void;
}) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{__('Name', 'yaycheckout')}</TableHead>
          {/*
           * Assignment column — Pro only, design shows it when a layout has
           * an assignment rule (role/country/campaign). No real data yet:
           * Layout::$rules (META_RULES) exists but is "Unused in 1.x" —
           * there's no rule-authoring UI or evaluation engine. Uncomment
           * once LayoutSummary carries a real `assignment` field.
           * <TableHead>{__('Assignment', 'yaycheckout')}</TableHead>
           */}
          <TableHead>{__('Status', 'yaycheckout')}</TableHead>
          <TableHead>{__('Active', 'yaycheckout')}</TableHead>
          <TableHead>{__('Last published', 'yaycheckout')}</TableHead>
          <TableHead>{__('Actions', 'yaycheckout')}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.map((layout) => (
          <LayoutTableRow key={layout.id} layout={layout} view={view} onEditLayout={onEditLayout} />
        ))}
      </TableBody>
    </Table>
  );
}

function statusBadge(layout: LayoutSummary) {
  if (layout.status === 'archived') {
    return <Badge variant="destructive-soft">{__('Archived', 'yaycheckout')}</Badge>;
  }

  if (layout.status === 'published') {
    return (
      <Badge variant="success">
        {layout.hasUnpublishedChanges
          ? __('Published · unpublished changes', 'yaycheckout')
          : __('Published', 'yaycheckout')}
      </Badge>
    );
  }

  return <Badge variant="outline">{__('Draft', 'yaycheckout')}</Badge>;
}

function LayoutTableRow({
  layout,
  view,
  onEditLayout,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
}) {
  return (
    <TableRow>
      <TableCell className="max-w-[320px] truncate font-medium" title={layout.title}>
        {layout.title}
      </TableCell>
      {/*
       * Pairs with the commented-out Assignment <TableHead> above — same
       * "no real data yet" reason. Original design:
       * <TableCell>
       *   {layout.assignment ? (
       *     layout.isActive ? (
       *       <span className="text-muted-foreground font-mono text-xs">{layout.assignment}</span>
       *     ) : (
       *       <Badge variant="outline">{layout.assignment}</Badge>
       *     )
       *   ) : (
       *     <span className="text-muted-foreground">—</span>
       *   )}
       * </TableCell>
       */}
      <TableCell>{statusBadge(layout)}</TableCell>
      <TableCell className="font-mono text-xs">
        {layout.isActive ? <Badge variant="success">{__('Active', 'yaycheckout')}</Badge> : '—'}
      </TableCell>
      <TableCell className="font-mono text-xs">{layout.publishedAt ?? __('never', 'yaycheckout')}</TableCell>
      <TableCell>
        <LayoutRowActions layout={layout} view={view} onEditLayout={onEditLayout} />
      </TableCell>
    </TableRow>
  );
}

/**
 * Edit and Publish are real; Publish is enabled by unpublished changes alone,
 * regardless of active state. The rest (Preview, Set active, "⋯", Restore,
 * Delete for good) match the demo's DOM state but have no onClick yet.
 */
function LayoutRowActions({
  layout,
  view,
  onEditLayout,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
}) {
  const publishMutation = usePublishLayoutMutation();

  if (view === 'archived') {
    return (
      <div className="flex items-center gap-2">
        <Button size="sm" variant="outline">
          {__('Preview', 'yaycheckout')}
        </Button>
        <Button size="sm" variant="outline">
          {__('Restore', 'yaycheckout')}
        </Button>
        <Button size="sm" variant="destructive-outline">
          {__('Delete for good', 'yaycheckout')}
        </Button>
      </div>
    );
  }

  const canPublish = layout.hasUnpublishedChanges;

  function onPublish() {
    publishMutation.mutate(layout.id, {
      onSuccess: () => toast.success(__('Layout published.', 'yaycheckout')),
    });
  }

  return (
    <div className="flex items-center gap-2">
      <Button size="sm" variant="outline" onClick={() => onEditLayout(layout.id)}>
        {__('Edit', 'yaycheckout')}
      </Button>
      <Button size="sm" variant="outline">
        {canPublish ? __('Preview draft', 'yaycheckout') : __('Preview', 'yaycheckout')}
      </Button>
      {canPublish && (
        <Button size="sm" onClick={onPublish} disabled={publishMutation.isPending}>
          {publishMutation.isPending ? __('Publishing…', 'yaycheckout') : __('Publish', 'yaycheckout')}
        </Button>
      )}
      {!layout.isActive && (
        <Button size="sm" variant="outline" disabled={layout.status !== 'published'}>
          {__('Set active', 'yaycheckout')}
        </Button>
      )}
      {layout.isActive && !canPublish && (
        <span className="text-muted-foreground text-xs">{__('active', 'yaycheckout')}</span>
      )}
      <Button size="sm" variant="outline">
        ⋯
      </Button>
    </div>
  );
}

/** Three loading rows in a Card — the same shape whether the whole page is
 * loading for the first time or one tab's table is waiting on its own fetch
 * (see the isPlaceholderData branch in LayoutsPageBody). */
function TableRowsSkeleton() {
  return (
    <Card>
      <div className="space-y-3">
        {[0, 1, 2].map((i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    </Card>
  );
}

function LayoutsPageSkeleton() {
  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
      <TableRowsSkeleton />
      <aside className="space-y-4">
        <Card>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-3 h-3 w-1/2" />
        </Card>
        <Card>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-3 h-3 w-1/2" />
        </Card>
      </aside>
    </div>
  );
}

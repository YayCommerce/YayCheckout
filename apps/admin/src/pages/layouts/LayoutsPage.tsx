import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Plus, Search } from 'lucide-react';
import { useDebounceFn } from 'rooks';
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
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Pagination } from '@/components/ui/pagination';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { NewLayoutModal } from '@/pages/layouts/new-layout/NewLayoutModal';
import PageLayout from '@/pages/PageLayout';

type View = 'listed' | 'archived';

const PAGE_SIZE = 10;

/*
 * GET /layouts (one page of items, matched against `search`) + GET
 * /layouts/counts (tab totals) from LayoutRepository — two separate queries
 * so counts has one cache key instead of diverging per status filter. Edit,
 * Publish, Search and pagination are real; Health/Compatibility, Preview, Set
 * active, "⋯", Restore and Delete for good render with the demo's exact DOM
 * state (enabled/disabled/hidden, labels) but no onClick — each still lacks a
 * backend action. See LayoutRowActions.
 */
export default function LayoutsPage() {
  const [view, setView] = useState<View>('listed');
  const [pageIndex, setPageIndex] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');

  // Reset to page 1 in the same state update as the filter change, not a
  // separate effect — an effect would commit a render with the new search
  // (or view) paired with the stale pageIndex first, firing one fetch for a
  // page that may not exist under the new filter before a second effect-
  // driven fetch corrects it.
  const [applySearch] = useDebounceFn((value: string) => {
    setSearch(value);
    setPageIndex(0);
  }, 300);

  function onSearchInputChange(value: string) {
    setSearchInput(value);
    applySearch(value);
  }

  function onViewChange(next: View) {
    setView(next);
    setPageIndex(0);
  }

  const list = useLayoutsQuery({
    status: view === 'archived' ? ['archived'] : ['draft', 'published'],
    search,
    page: pageIndex + 1,
    perPage: PAGE_SIZE,
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
       * on later tab/page/search changes, and `counts` has no status filter
       * to switch at all, so neither re-fires isPending after the first
       * load. */}
      {isPending && <LayoutsPageSkeleton />}
      {error && <p className="text-destructive text-sm">{error.message}</p>}
      {list.data && countsQuery.data && (
        <LayoutsPageBody
          data={list.data}
          counts={countsQuery.data}
          isFetching={list.isFetching}
          isPlaceholderData={list.isPlaceholderData}
          view={view}
          onViewChange={onViewChange}
          search={search}
          searchInput={searchInput}
          onSearchInputChange={onSearchInputChange}
          pageIndex={pageIndex}
          onPageIndexChange={setPageIndex}
        />
      )}
    </PageLayout>
  );
}

function LayoutsPageBody({
  data,
  counts,
  isFetching,
  isPlaceholderData,
  view,
  onViewChange,
  search,
  searchInput,
  onSearchInputChange,
  pageIndex,
  onPageIndexChange,
}: {
  data: LayoutListResponse;
  counts: LayoutCounts;
  isFetching: boolean;
  isPlaceholderData: boolean;
  view: View;
  onViewChange: (view: View) => void;
  search: string;
  searchInput: string;
  onSearchInputChange: (value: string) => void;
  pageIndex: number;
  onPageIndexChange: (pageIndex: number) => void;
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

  // A tab/page/search change (isPlaceholderData) disables tabs and
  // pagination together; a same-page refetch (e.g. after creating a draft)
  // doesn't.
  const controlsDisabled = isFetching && isPlaceholderData;

  // Only flashes a skeleton when a count's actual value changes (e.g. after
  // creating a draft) — a background revalidation that resolves to the same
  // number, or a plain tab switch, never touches it. See useFlashOnChange.
  const listedFlashing = useFlashOnChange(counts.listed);
  const archivedFlashing = useFlashOnChange(counts.archived);

  // Search and pagination only earn their place once a tab is long enough to
  // need them — same >=10 threshold the dump-data design used for the static
  // demo (docs/superpowers/plans/2026-09-11-layouts-dump-data-design.md, case
  // edge-many-long-names), now driving both controls from one flag.
  const showListControls = (view === 'archived' ? counts.archived : counts.listed) >= PAGE_SIZE;
  const pageCount = Math.max(1, Math.ceil(data.total / PAGE_SIZE));

  return (
    <div className="space-y-4">
      <NewLayoutModal open={newLayoutOpen} onOpenChange={setNewLayoutOpen} />

      <div className="grid gap-6 lg:grid-cols-[1fr_200px]">
        <Card className="min-w-0">
          <h2 className="text-lg font-semibold">{__('Your layouts', 'yaycheckout')}</h2>

          <div className="flex flex-wrap items-center justify-between gap-3">
            <Segmented
              size="sm"
              value={view}
              onValueChange={(next) => next && onViewChange(next as View)}
              disabled={controlsDisabled}
            >
              <SegmentedItem value="listed">
                {__('Active list', 'yaycheckout')}
                <Badge variant="dimmed" className="h-5.5 min-w-5.5 rounded-full px-1 font-normal">
                  <CountValue flashing={listedFlashing} value={counts.listed} />
                </Badge>
              </SegmentedItem>
              <SegmentedItem value="archived">
                {__('Archive', 'yaycheckout')}
                <Badge variant="dimmed" className="h-5.5 min-w-5.5 rounded-full px-1 font-normal">
                  <CountValue flashing={archivedFlashing} value={counts.archived} />
                </Badge>
              </SegmentedItem>
            </Segmented>

            <div className="flex items-center gap-3">
              {showListControls && (
                <InputGroup size="small" className="w-64">
                  <InputGroupInput
                    placeholder={__('Search layouts…', 'yaycheckout')}
                    value={searchInput}
                    onChange={(e) => onSearchInputChange(e.target.value)}
                  />
                  <InputGroupAddon align="inline-end">
                    <Search className="size-4" />
                  </InputGroupAddon>
                </InputGroup>
              )}
              <Button size="sm" variant="primary-outline" onClick={() => setNewLayoutOpen(true)}>
                <Plus className="size-4" />
                {__('Add layout', 'yaycheckout')}
              </Button>
            </div>
          </div>

          {isPlaceholderData ? (
            <TableRowsSkeleton />
          ) : data.items.length === 0 ? (
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon" />
                <EmptyTitle>
                  {search
                    ? __('No matching layouts', 'yaycheckout')
                    : view === 'archived'
                      ? __('Nothing archived', 'yaycheckout')
                      : __('Nothing here yet', 'yaycheckout')}
                </EmptyTitle>
                <EmptyDescription>
                  {search
                    ? __('No layouts match your search. Try a different name.', 'yaycheckout')
                    : view === 'archived'
                      ? __('Layouts you archive will show up here.', 'yaycheckout')
                      : counts.archived > 0
                        ? __(
                            'All of your layouts are archived. Restore one to bring it back to this list, or start a new draft. Archiving never deletes anything.',
                            'yaycheckout',
                          )
                        : __('Create your first checkout layout to get started.', 'yaycheckout')}
                </EmptyDescription>
              </EmptyHeader>
              {view === 'listed' && !search && (
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
          ) : (
            <div className={cn('min-w-0 transition-opacity', isFetching && 'opacity-60')}>
              <div className="overflow-x-auto rounded-lg border">
                <LayoutsTable items={data.items} view={view} onEditLayout={setEditingLayoutId} />
              </div>
              {showListControls && (
                <div className="mt-3 flex items-center justify-end gap-4">
                  <Pagination
                    pageIndex={pageIndex}
                    pageCount={pageCount}
                    onPreviousPage={() => onPageIndexChange(pageIndex - 1)}
                    onNextPage={() => onPageIndexChange(pageIndex + 1)}
                    onPageChange={onPageIndexChange}
                    canPreviousPage={pageIndex > 0}
                    canNextPage={pageIndex + 1 < pageCount}
                    disabled={controlsDisabled}
                  />
                </div>
              )}
            </div>
          )}
        </Card>

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

function CountValue({ flashing, value }: { flashing: boolean; value: number }) {
  return flashing ? <Skeleton className="inline-block size-3 align-middle" /> : <>{value}</>;
}

/**
 * True for FLASH_MS right after `value` changes from what it was last time;
 * false otherwise, including on mount and when a refetch resolves to the
 * same number. LayoutRestController::get_layouts() always returns both
 * counts regardless of the `status` filter, so a plain tab switch or a
 * same-value revalidation never trips this — only a genuine change does
 * (e.g. counts.listed after creating a draft).
 */
const FLASH_MS = 400;

function useFlashOnChange(value: number): boolean {
  const [flashing, setFlashing] = useState(false);
  const previous = useRef(value);

  useEffect(() => {
    if (previous.current === value) {
      return;
    }

    previous.current = value;
    setFlashing(true);
    const timeout = setTimeout(() => setFlashing(false), FLASH_MS);
    return () => clearTimeout(timeout);
  }, [value]);

  return flashing;
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

/** Three loading rows — the same shape whether the whole page is loading for
 * the first time (wrapped in its own Card, see LayoutsPageSkeleton) or one
 * tab's table is waiting on its own fetch inside the already-rendered
 * "Your layouts" Card (see the isPlaceholderData branch in LayoutsPageBody). */
function TableRowsSkeleton() {
  return (
    <div className="space-y-3">
      {[0, 1, 2].map((i) => (
        <Skeleton key={i} className="h-10 w-full" />
      ))}
    </div>
  );
}

function LayoutsPageSkeleton() {
  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_200px]">
      <Card>
        <TableRowsSkeleton />
      </Card>
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

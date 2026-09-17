import { useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Plus, Search } from 'lucide-react';
import { useDebounceFn } from 'rooks';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import {
  LAYOUT_KEYS,
  useActivateLayoutMutation,
  useArchiveLayoutMutation,
  useDeactivateLayoutMutation,
  useDuplicateLayoutMutation,
  useLayoutCountsQuery,
  useLayoutsQuery,
} from '@/lib/queries/layout.queries';
import {
  LISTED_LAYOUT_STATUSES,
  type LayoutCounts,
  type LayoutListResponse,
  type LayoutSummary,
} from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Pagination } from '@/components/ui/pagination';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArchiveModal } from '@/pages/layouts/ArchiveModal';
import { DeactivateModal } from '@/pages/layouts/DeactivateModal';
import { NewLayoutModal } from '@/pages/layouts/new-layout/NewLayoutModal';
import { SetActiveModal } from '@/pages/layouts/SetActiveModal';
import PageLayout from '@/pages/PageLayout';

type View = 'listed' | 'archived';

const MIN_ITEMS_FOR_LIST_CONTROLS = 10;

/*
 * GET /layouts (one page of items, matched against `search`) + GET
 * /layouts/counts (tab totals) from LayoutRepository — two separate queries
 * so counts has one cache key instead of diverging per status filter. Edit,
 * Set active, Deactivate, Duplicate, Archive, Search and pagination are
 * real; Preview, Restore and Delete for good render with the designed DOM
 * state (enabled/disabled/hidden, labels) but no onClick — see
 * LayoutRowActions — and Health/Compatibility are "Not available yet."
 * placeholders.
 */
export default function LayoutsPage() {
  const [view, setView] = useState<View>('listed');
  const [pageIndex, setPageIndex] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  // Mirrors searchInput for the trailing debounce callback, which closes over
  // a stale render. rooks' useDebounceFn exposes no cancel, so the callback
  // checks this instead when a tab switch has cleared the input under it.
  const searchInputRef = useRef('');

  // Reset to page 1 in the same state update as the filter change, not a
  // separate effect — an effect would commit a render with the new search
  // (or view) paired with the stale pageIndex first, firing one fetch for a
  // page that may not exist under the new filter before a second effect-
  // driven fetch corrects it.
  const [applySearch] = useDebounceFn((value: string) => {
    if (value !== searchInputRef.current) return;

    setSearch(value);
    setPageIndex(0);
  }, 300);

  function onSearchInputChange(value: string) {
    searchInputRef.current = value;
    setSearchInput(value);
    applySearch(value);
  }

  function onViewChange(next: View) {
    searchInputRef.current = '';
    setView(next);
    setSearchInput('');
    setSearch('');
    setPageIndex(0);
  }

  const list = useLayoutsQuery({
    status: view === 'archived' ? ['archived'] : LISTED_LAYOUT_STATUSES,
    search,
    page: pageIndex + 1,
  });
  const countsQuery = useLayoutCountsQuery();

  // View whose rows are on screen. Lags `view` only while the new tab's first
  // fetch is in flight — the one case keepPreviousData's placeholder rows
  // belong to a different tab and must not render under this tab's heading.
  // A same-tab page/search change keeps its placeholder rows and dims them.
  const [settledView, setSettledView] = useState(view);
  if (list.data && !list.isPlaceholderData && settledView !== view) setSettledView(view);

  const listIsOtherTabPlaceholder = list.isPlaceholderData && settledView !== view;

  const isPending = list.isPending || countsQuery.isPending;

  return (
    <PageLayout
      heading={__('Layouts', 'yaycheckout')}
      description={__('Create, publish and activate checkout layouts.', 'yaycheckout')}
    >
      {/* isPending only fires once, before both queries have loaded —
       * `list`'s placeholderData: keepPreviousData flips isFetching instead
       * on later tab/page/search changes, and `counts` has no status filter
       * to switch at all, so neither re-fires isPending after the first
       * load. The body is gated on it too: counts usually lands first, and
       * without the gate the real tabs would render under the skeleton. */}
      {isPending && <LayoutsPageSkeleton />}
      {/* Only a counts failure replaces the page — the tabs can't render
       * without it. A list failure is passed into the body instead, so the
       * tabs stay mounted and the user can switch away or back to retry;
       * placeholderData only covers `pending`, so on error `list.data` is
       * undefined and gating the body on it would unmount the tabs too. */}
      {countsQuery.error && <p className="text-destructive text-sm">{countsQuery.error.message}</p>}
      {!isPending && countsQuery.data && (
        <LayoutsPageBody
          listData={list.data}
          listError={list.error}
          listFetching={list.isFetching}
          listIsOtherTabPlaceholder={listIsOtherTabPlaceholder}
          counts={countsQuery.data}
          countsFetching={countsQuery.isFetching}
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
  listData,
  listError,
  listFetching,
  listIsOtherTabPlaceholder,
  counts,
  countsFetching,
  view,
  onViewChange,
  search,
  searchInput,
  onSearchInputChange,
  pageIndex,
  onPageIndexChange,
}: {
  listData: LayoutListResponse | undefined;
  listError: Error | null;
  /** Any fetch for this tab (refetch, page, search) — rows stay on screen and dim. */
  listFetching: boolean;
  /** listData still belongs to the previous tab while this tab's first fetch is in flight. */
  listIsOtherTabPlaceholder: boolean;
  counts: LayoutCounts;
  countsFetching: boolean;
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
  const [activatingLayout, setActivatingLayout] = useState<LayoutSummary | null>(null);
  const [deactivatingLayout, setDeactivatingLayout] = useState<LayoutSummary | null>(null);
  const [archivingLayout, setArchivingLayout] = useState<LayoutSummary | null>(null);

  const activateMutation = useActivateLayoutMutation();
  const deactivateMutation = useDeactivateLayoutMutation();
  const archiveMutation = useArchiveLayoutMutation();
  const duplicateMutation = useDuplicateLayoutMutation();

  /*
   * Both mutations invalidate the list and the counts rather than patching
   * rows here: activating one layout deactivates another under Free, so the
   * row that changed is not only the row that was clicked. The server already
   * decides that — see LayoutRepository::set_active() — and is_active on every
   * refetched row reflects it.
   */
  function onActivate(layout: LayoutSummary) {
    activateMutation.mutate(layout.id, {
      onSuccess: () => {
        setActivatingLayout(null);
        toast.success(__('Layout activated.', 'yaycheckout'));
      },
    });
  }

  function onDeactivate(layout: LayoutSummary) {
    deactivateMutation.mutate(layout.id, {
      onSuccess: () => {
        setDeactivatingLayout(null);
        toast.success(__('Layout deactivated.', 'yaycheckout'));
      },
    });
  }

  function onArchive(layout: LayoutSummary) {
    archiveMutation.mutate(layout.id, {
      onSuccess: () => {
        setArchivingLayout(null);
        toast.success(__('Layout archived.', 'yaycheckout'));

        if (listData?.items.length === 1 && pageIndex > 0) {
          onPageIndexChange(pageIndex - 1);
        }
      },
    });
  }

  function onDuplicate(layout: LayoutSummary) {
    duplicateMutation.mutate(layout.id, {
      onSuccess: () => toast.success(__('Layout duplicated.', 'yaycheckout')),
    });
  }

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

  // Disabled only while switching tabs — the one fetch whose rows are hidden
  // behind a skeleton. Page and search fetches keep the controls live.
  const controlsDisabled = listIsOtherTabPlaceholder;

  // >=10 threshold per the Layouts screen design (many long names).
  const showListControls = (view === 'archived' ? counts.archived : counts.listed) >= MIN_ITEMS_FOR_LIST_CONTROLS;
  // Floor at 1: totalPages is 0 for an empty result.
  const pageCount = Math.max(1, listData?.totalPages ?? 1);

  return (
    <div className="space-y-4">
      <NewLayoutModal open={newLayoutOpen} onOpenChange={setNewLayoutOpen} />
      <SetActiveModal
        layout={activatingLayout}
        onOpenChange={(open) => !open && setActivatingLayout(null)}
        onConfirm={onActivate}
        isPending={activateMutation.isPending}
      />
      <DeactivateModal
        layout={deactivatingLayout}
        isLastActive={counts.active === 1}
        onOpenChange={(open) => !open && setDeactivatingLayout(null)}
        onConfirm={onDeactivate}
        isPending={deactivateMutation.isPending}
      />
      <ArchiveModal
        layout={archivingLayout}
        isLastActive={counts.active === 1}
        onOpenChange={(open) => !open && setArchivingLayout(null)}
        onConfirm={onArchive}
        isPending={archiveMutation.isPending}
      />

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
                  <CountValue loading={countsFetching} value={counts.listed} />
                </Badge>
              </SegmentedItem>
              <SegmentedItem value="archived">
                {__('Archive', 'yaycheckout')}
                <Badge variant="dimmed" className="h-5.5 min-w-5.5 rounded-full px-1 font-normal">
                  <CountValue loading={countsFetching} value={counts.archived} />
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

          {/* A failed background refetch keeps the previous `listData`, so
           * the table still renders under the error; a failed first fetch of
           * a tab has no data and shows only the error, tabs still usable. */}
          {listError && <p className="text-destructive text-sm">{listError.message}</p>}

          {/* Placeholder rows from the other tab never render under this
           * tab's heading — skeleton until its own arrive. Same-tab page and
           * search fetches keep the previous rows (or empty state) and dim
           * them, so the pager stays mounted and nothing jumps. */}
          {listIsOtherTabPlaceholder ? (
            <TableRowsSkeleton />
          ) : !listData ? null : (
            <div className={cn('min-w-0 transition-opacity', listFetching && 'opacity-60')}>
              {listData.items.length === 0 ? (
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
                <>
                  <div className="overflow-x-auto rounded-lg border">
                    <LayoutsTable
                      items={listData.items}
                      view={view}
                      onEditLayout={setEditingLayoutId}
                      onSetActive={setActivatingLayout}
                      onDeactivate={setDeactivatingLayout}
                      onArchive={setArchivingLayout}
                      onDuplicate={onDuplicate}
                    />
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
                </>
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

/**
 * Shows a skeleton while the counts query is refetching (create draft, close
 * editor, activate/deactivate, stale-on-focus) so a slow site never displays a stale number as if
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

function LayoutsTable({
  items,
  view,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onDuplicate,
}: {
  items: LayoutSummary[];
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
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
          <LayoutTableRow
            key={layout.id}
            layout={layout}
            view={view}
            onEditLayout={onEditLayout}
            onSetActive={onSetActive}
            onDeactivate={onDeactivate}
            onArchive={onArchive}
            onDuplicate={onDuplicate}
          />
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

  if (layout.status === 'scheduled') {
    return (
      <Badge variant="outline">
        {layout.scheduledAt
          ? sprintf(
              /* translators: %s: scheduled publish date */
              __('Scheduled · %s', 'yaycheckout'),
              layout.scheduledAt,
            )
          : __('Scheduled', 'yaycheckout')}
      </Badge>
    );
  }

  if (layout.status === 'pending') {
    return <Badge variant="outline">{__('Pending review', 'yaycheckout')}</Badge>;
  }

  return <Badge variant="outline">{__('Draft', 'yaycheckout')}</Badge>;
}

function LayoutTableRow({
  layout,
  view,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onDuplicate,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
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
        <LayoutRowActions
          layout={layout}
          view={view}
          onEditLayout={onEditLayout}
          onSetActive={onSetActive}
          onDeactivate={onDeactivate}
          onArchive={onArchive}
          onDuplicate={onDuplicate}
        />
      </TableCell>
    </TableRow>
  );
}

/**
 * Edit, Set active and the "⋯" menu's Duplicate, Deactivate and Archive are
 * real; Deactivate is disabled on a row that isn't active, Archive never is —
 * an active row can be archived, and ArchiveModal warns about it.
 * Unpublished changes show as a badge and a "Preview draft" label but carry no
 * Publish button — publishing is the editor's job. The rest (Preview, Restore,
 * Delete for good) match the designed DOM state but have no onClick yet.
 */
function LayoutRowActions({
  layout,
  view,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onDuplicate,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
}) {
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

  // Only changes the Preview label: publishing happens in the editor, not from
  // this list.
  const canPublish = layout.hasUnpublishedChanges;

  return (
    <div className="flex items-center gap-2">
      <Button size="sm" variant="outline" onClick={() => onEditLayout(layout.id)}>
        {__('Edit', 'yaycheckout')}
      </Button>
      <Button size="sm" variant="outline">
        {canPublish ? __('Preview draft', 'yaycheckout') : __('Preview', 'yaycheckout')}
      </Button>
      {/* Rendered on every row and disabled once active, so the action column
       * keeps the same shape from row to row instead of shifting. */}
      <Button size="sm" variant="outline" disabled={layout.isActive} onClick={() => onSetActive(layout)}>
        {__('Set active', 'yaycheckout')}
      </Button>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button size="sm" variant="outline">
            ⋯
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onSelect={() => onDuplicate(layout)}>{__('Duplicate', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem variant="destructive" disabled={!layout.isActive} onSelect={() => onDeactivate(layout)}>
            {__('Deactivate', 'yaycheckout')}
          </DropdownMenuItem>
          <DropdownMenuItem onSelect={() => onArchive(layout)}>{__('Archive', 'yaycheckout')}</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

/** Three loading rows — the same shape whether the whole page is loading for
 * the first time (wrapped in its own Card, see LayoutsPageSkeleton) or one
 * tab's table is waiting on its own fetch inside the already-rendered
 * "Your layouts" Card (see the listIsOtherTabPlaceholder branch in LayoutsPageBody). */
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

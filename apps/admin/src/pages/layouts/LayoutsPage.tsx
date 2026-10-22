import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowDown, ArrowUp, ArrowUpDown, Loader2, MoreHorizontal, Plus, Search } from 'lucide-react';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { toast } from 'sonner';
import { __, _n, sprintf } from '@wordpress/i18n';

import { formatDateAtTime } from '@/lib/helpers/date.helper';
import { editLayoutIdFrom, editViewFrom, withEditLayout, type EditView } from '@/lib/helpers/layout.helper';
import { isPro } from '@/lib/plan';
import {
  LAYOUT_KEYS,
  useActivateLayoutMutation,
  useArchiveLayoutMutation,
  useBulkLayoutsMutation,
  useCreatePreviewLinkMutation,
  useDeactivateLayoutMutation,
  useDeleteLayoutMutation,
  useDuplicateLayoutMutation,
  useLayoutCountsQuery,
  useLayoutsQuery,
  useRestoreLayoutMutation,
} from '@/lib/queries/layout.queries';
import {
  LISTED_LAYOUT_STATUSES,
  type LayoutBulkAction,
  type LayoutBulkResult,
  type LayoutCounts,
  type LayoutListResponse,
  type LayoutSummary,
  type PreviewLink,
} from '@/lib/schema/layout.schema';
import { cn } from '@/lib/utils';
import { useDebouncedSearch } from '@/hooks/useDebouncedSearch';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { LayoutStatusBadge } from '@/components/shared/LayoutStatusBadge';
import { ArchiveModal } from '@/pages/layouts/ArchiveModal';
import { AssignmentChips } from '@/pages/layouts/assignment/AssignmentChips';
import { AssignmentDrawer } from '@/pages/layouts/assignment/AssignmentDrawer';
import { BulkActionDialog, type ConfirmedBulkAction } from '@/pages/layouts/BulkActionDialog';
import { DeactivateModal } from '@/pages/layouts/DeactivateModal';
import { DeleteModal } from '@/pages/layouts/DeleteModal';
import { LayoutBulkBar } from '@/pages/layouts/LayoutBulkBar';
import { NewLayoutModal } from '@/pages/layouts/new-layout/NewLayoutModal';
import { PreviewLinkModal } from '@/pages/layouts/PreviewLinkModal';
import { RenameModal } from '@/pages/layouts/RenameModal';
import { SetActiveModal } from '@/pages/layouts/SetActiveModal';
import PageLayout from '@/pages/PageLayout';

type View = 'listed' | 'active' | 'archived';

/** Last-updated sort; null keeps the server's default order, newest created first. */
type Sort = 'asc' | 'desc' | null;

const MIN_ITEMS_FOR_LIST_CONTROLS = 10;

export default function LayoutsPage() {
  const [view, setView] = useState<View>('listed');
  const [pageIndex, setPageIndex] = useState(0);
  const [sort, setSort] = useState<Sort>(null);
  const {
    input: searchInput,
    search,
    setInput: onSearchInputChange,
    reset: resetSearch,
  } = useDebouncedSearch(() => setPageIndex(0));

  function onViewChange(next: View) {
    setView(next);
    resetSearch();
    setPageIndex(0);
  }

  function onSortChange(next: Sort) {
    setSort(next);
    setPageIndex(0);
  }

  const list = useLayoutsQuery({
    status: view === 'archived' ? ['trash'] : LISTED_LAYOUT_STATUSES,
    active: view === 'active',
    search,
    page: pageIndex + 1,
    ...(sort && { orderBy: 'modified' as const, order: sort }),
  });
  const countsQuery = useLayoutCountsQuery();

  // Lags `view` while a new tab's first fetch is in flight, so keepPreviousData's
  // rows from the other tab never render under this tab's heading.
  const [settledView, setSettledView] = useState(view);
  if (list.data && !list.isPlaceholderData && settledView !== view) setSettledView(view);

  const listIsOtherTabPlaceholder = list.isPlaceholderData && settledView !== view;

  const isPending = list.isPending || countsQuery.isPending;

  return (
    <PageLayout
      title={__('Layouts', 'yaycheckout')}
      description={__('Create and activate checkout layouts.', 'yaycheckout')}
    >
      {/* isPending fires only on first load; keepPreviousData turns later fetches into isFetching. */}
      {isPending && <LayoutsPageSkeleton />}
      {/* Only a counts failure replaces the page; a list failure is shown inside it so the tabs stay mounted. */}
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
          sort={sort}
          onSortChange={onSortChange}
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
  sort,
  onSortChange,
}: {
  listData: LayoutListResponse | undefined;
  listError: Error | null;
  /** Rows stay on screen and dim. */
  listFetching: boolean;
  /** listData still belongs to the previous tab. */
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
  sort: Sort;
  onSortChange: (sort: Sort) => void;
}) {
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const [newLayoutOpen, setNewLayoutOpen] = useState(false);
  // The URL is the only record of the open editor, so browser Back closes it and other screens can deep-link to it.
  const editingLayoutId = editLayoutIdFrom(searchParams);
  const editingView = editViewFrom(searchParams);
  // The state marks an entry this page pushed, so closing pops it instead of leaving a duplicate behind.
  const openEditor = (id: number, editView: EditView = 'main') =>
    setSearchParams(withEditLayout(searchParams, id, editView), { state: { isEditorPushed: true } });
  const lastEditingLayoutId = useRef(editingLayoutId);
  const [activatingLayout, setActivatingLayout] = useState<LayoutSummary | null>(null);
  const [deactivatingLayout, setDeactivatingLayout] = useState<LayoutSummary | null>(null);
  const [renamingLayout, setRenamingLayout] = useState<LayoutSummary | null>(null);
  const [archivingLayout, setArchivingLayout] = useState<LayoutSummary | null>(null);
  const [deletingLayout, setDeletingLayout] = useState<LayoutSummary | null>(null);
  const [assigningLayout, setAssigningLayout] = useState<LayoutSummary | null>(null);
  const [previewing, setPreviewing] = useState<{ layout: LayoutSummary; link: PreviewLink } | null>(null);
  const [selectedIds, setSelectedIds] = useState<ReadonlySet<number>>(new Set());
  const [confirmingBulk, setConfirmingBulk] = useState<ConfirmedBulkAction | null>(null);

  const activateMutation = useActivateLayoutMutation();
  const deactivateMutation = useDeactivateLayoutMutation();
  const archiveMutation = useArchiveLayoutMutation();
  const restoreMutation = useRestoreLayoutMutation();
  const deleteMutation = useDeleteLayoutMutation();
  const duplicateMutation = useDuplicateLayoutMutation();
  const previewLinkMutation = useCreatePreviewLinkMutation();
  const bulkMutation = useBulkLayoutsMutation();

  // A selection covers the rows on screen only.
  useEffect(() => setSelectedIds(new Set()), [view, pageIndex, search, sort]);

  const rows = listIsOtherTabPlaceholder ? [] : (listData?.items ?? []);
  const selectedRows = rows.filter((layout) => selectedIds.has(layout.id));

  /** The selected rows an action changes: Deactivate skips those already inactive. */
  function bulkTargets(action: LayoutBulkAction) {
    return action === 'deactivate' ? selectedRows.filter((layout) => layout.isActive) : selectedRows;
  }

  function onBulkAction(action: LayoutBulkAction) {
    // Restore runs at once, as the row's Restore does.
    if (action === 'restore') {
      runBulk(action);
    } else {
      setConfirmingBulk(action);
    }
  }

  function runBulk(action: LayoutBulkAction) {
    bulkMutation.mutate(
      { action, ids: bulkTargets(action).map((layout) => layout.id) },
      {
        onSuccess: (result) => {
          setConfirmingBulk(null);
          // Refused rows stay selected, to retry or act on otherwise.
          setSelectedIds(new Set(result.failed.map((failure) => failure.id)));
          toastBulkResult(action, result);

          // Deactivated rows stay on the All tab; every other action takes them off this tab.
          if (action !== 'deactivate' || view === 'active') stepBackIfPageEmptied(result.done.length);
        },
      },
    );
  }

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

        // The row leaves the Active tab.
        if (view === 'active') stepBackIfPageEmptied();
      },
    });
  }

  /** After rows leave the list: when they were the last on a later page, that page no longer exists. */
  function stepBackIfPageEmptied(removed = 1) {
    if (listData && removed >= listData.items.length && pageIndex > 0) {
      onPageIndexChange(pageIndex - 1);
    }
  }

  function onArchive(layout: LayoutSummary) {
    archiveMutation.mutate(layout.id, {
      onSuccess: () => {
        setArchivingLayout(null);
        toast.success(__('Layout archived.', 'yaycheckout'));

        stepBackIfPageEmptied();
      },
    });
  }

  function onRestore(layout: LayoutSummary) {
    restoreMutation.mutate(layout.id, {
      onSuccess: () => {
        toast.success(__('Layout restored.', 'yaycheckout'));

        stepBackIfPageEmptied();
      },
    });
  }

  function onDelete(layout: LayoutSummary) {
    deleteMutation.mutate(layout.id, {
      onSuccess: () => {
        setDeletingLayout(null);
        toast.success(__('Layout deleted.', 'yaycheckout'));

        stepBackIfPageEmptied();
      },
    });
  }

  function onDuplicate(layout: LayoutSummary) {
    duplicateMutation.mutate(layout.id, {
      onSuccess: () => toast.success(__('Layout duplicated.', 'yaycheckout')),
    });
  }

  function onPreview(layout: LayoutSummary) {
    previewLinkMutation.mutate(layout.id, {
      onSuccess: (link) => setPreviewing({ layout, link }),
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
    // LayoutEditorEmbed::QUERY_VIEW; view_urls() there builds the same URLs.
    if (editingView === 'thank-you') {
      url.searchParams.set('yc-view', 'thank-you');
    }
    return url.toString();
  }, [editingLayoutId, editingView]);

  // Refetch once the editor closes. Watched on the URL, not the dialog: browser Back closes it without onOpenChange.
  useEffect(() => {
    if (lastEditingLayoutId.current !== null && editingLayoutId === null) {
      queryClient.invalidateQueries({ queryKey: LAYOUT_KEYS.all });
    }
    lastEditingLayoutId.current = editingLayoutId;
  }, [editingLayoutId, queryClient]);

  const onEditorOpenChange = (open: boolean) => {
    if (open) {
      return;
    }
    if (location.state?.isEditorPushed) {
      navigate(-1);
    } else {
      setSearchParams(withEditLayout(searchParams, null), { replace: true });
    }
  };

  const controlsDisabled = listIsOtherTabPlaceholder;

  const viewCount = view === 'archived' ? counts.archived : view === 'active' ? counts.active : counts.listed;
  const showListControls = viewCount >= MIN_ITEMS_FOR_LIST_CONTROLS;
  // totalPages is 0 for an empty result.
  const pageCount = Math.max(1, listData?.totalPages ?? 1);

  return (
    <div className="space-y-4">
      <NewLayoutModal open={newLayoutOpen} onOpenChange={setNewLayoutOpen} onCreated={openEditor} />
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
      <RenameModal layout={renamingLayout} onOpenChange={(open) => !open && setRenamingLayout(null)} />
      <ArchiveModal
        layout={archivingLayout}
        onOpenChange={(open) => !open && setArchivingLayout(null)}
        onConfirm={onArchive}
        isPending={archiveMutation.isPending}
      />
      <DeleteModal
        layout={deletingLayout}
        onOpenChange={(open) => !open && setDeletingLayout(null)}
        onConfirm={onDelete}
        isPending={deleteMutation.isPending}
      />
      <BulkActionDialog
        action={confirmingBulk}
        layouts={confirmingBulk ? bulkTargets(confirmingBulk) : []}
        leavesNoneActive={confirmingBulk === 'deactivate' && bulkTargets('deactivate').length >= counts.active}
        onOpenChange={(open) => !open && setConfirmingBulk(null)}
        onConfirm={() => confirmingBulk && runBulk(confirmingBulk)}
        isPending={bulkMutation.isPending}
      />
      <AssignmentDrawer layout={assigningLayout} onOpenChange={(open) => !open && setAssigningLayout(null)} />
      <PreviewLinkModal
        layout={previewing?.layout ?? null}
        link={previewing?.link ?? null}
        onOpenChange={(open) => !open && setPreviewing(null)}
      />

      <Card className="min-w-0">
        <h2 className="text-xl leading-none font-semibold">{__('Your layouts', 'yaycheckout')}</h2>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <Segmented
            size="sm"
            value={view}
            onValueChange={(next) => next && onViewChange(next as View)}
            disabled={controlsDisabled}
          >
            <SegmentedItem value="listed">
              {__('All', 'yaycheckout')}
              <Badge variant="dimmed" className="h-5.5 min-w-5.5 rounded-full px-1 font-normal">
                <CountValue loading={countsFetching} value={counts.listed} />
              </Badge>
            </SegmentedItem>
            <SegmentedItem value="active">
              {__('Active', 'yaycheckout')}
              <Badge variant="dimmed" className="h-5.5 min-w-5.5 rounded-full px-1 font-normal">
                <CountValue loading={countsFetching} value={counts.active} />
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

        {listError && <p className="text-destructive text-sm">{listError.message}</p>}

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
                        : view === 'active'
                          ? __('No active layouts', 'yaycheckout')
                          : __('Nothing here yet', 'yaycheckout')}
                  </EmptyTitle>
                  <EmptyDescription>
                    {search
                      ? __('No layouts match your search. Try a different name.', 'yaycheckout')
                      : view === 'archived'
                        ? __('Layouts you archive will show up here.', 'yaycheckout')
                        : view === 'active'
                          ? __('Set a layout active to use it on your checkout.', 'yaycheckout')
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
                    selectedIds={selectedIds}
                    onSelectedIdsChange={setSelectedIds}
                    onEditLayout={openEditor}
                    onSetActive={setActivatingLayout}
                    onDeactivate={setDeactivatingLayout}
                    onArchive={setArchivingLayout}
                    onRestore={onRestore}
                    onDelete={setDeletingLayout}
                    onDuplicate={onDuplicate}
                    onRename={setRenamingLayout}
                    onAssign={setAssigningLayout}
                    onPreview={onPreview}
                    previewPending={previewLinkMutation.isPending}
                    sort={sort}
                    onSortChange={onSortChange}
                    sortDisabled={controlsDisabled}
                  />
                </div>
                <div className="mt-3 flex flex-wrap items-center justify-between gap-4">
                  <LayoutBulkBar
                    selected={selectedRows}
                    archived={view === 'archived'}
                    onClear={() => setSelectedIds(new Set())}
                    onAction={onBulkAction}
                    disabled={bulkMutation.isPending || listFetching}
                  />
                  {showListControls && (
                    <Pagination
                      // Stays right while the bulk bar is hidden.
                      className="ms-auto"
                      pageIndex={pageIndex}
                      pageCount={pageCount}
                      onPreviousPage={() => onPageIndexChange(pageIndex - 1)}
                      onNextPage={() => onPageIndexChange(pageIndex + 1)}
                      onPageChange={onPageIndexChange}
                      canPreviousPage={pageIndex > 0}
                      canNextPage={pageIndex + 1 < pageCount}
                      disabled={controlsDisabled}
                    />
                  )}
                </div>
              </>
            )}
          </div>
        )}
      </Card>

      <Dialog open={null !== editingLayoutId} onOpenChange={onEditorOpenChange}>
        <DialogContent className="data-[state=open]:zoom-in-100 data-[state=closed]:zoom-out-100 inset-0 !flex h-dvh w-screen max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-none border-0 p-0 shadow-none sm:max-w-none">
          <DialogHeader className="shrink-0 gap-1 border-b px-4 py-2">
            <DialogTitle className="text-base">{__('Edit layout', 'yaycheckout')}</DialogTitle>
          </DialogHeader>

          {editorUrl && <LayoutEditorFrame key={editorUrl} src={editorUrl} />}
        </DialogContent>
      </Dialog>
    </div>
  );
}

/** Block-editor iframe with a spinner over it until the editor page finishes loading. Keyed by URL so each open starts in the loading state. */
function LayoutEditorFrame({ src }: { src: string }) {
  const [loaded, setLoaded] = useState(false);

  return (
    <div className="relative min-h-0 flex-1 overflow-hidden">
      <iframe
        title={__('Layout editor', 'yaycheckout')}
        src={src}
        onLoad={() => setLoaded(true)}
        className={cn('block size-full border-0', !loaded && 'invisible')}
      />
      {!loaded && (
        <div className="bg-background absolute inset-0 flex items-center justify-center">
          <Loader2
            className="text-muted-foreground size-6 animate-spin"
            aria-label={__('Loading editor…', 'yaycheckout')}
          />
        </div>
      )}
    </div>
  );
}

/** Tab count, or a skeleton while the counts query refetches. Its fade-in is CSS-delayed so a fast refetch unmounts it before it shows. */
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
  selectedIds,
  onSelectedIdsChange,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onRestore,
  onDelete,
  onDuplicate,
  onRename,
  onAssign,
  onPreview,
  previewPending,
  sort,
  onSortChange,
  sortDisabled,
}: {
  items: LayoutSummary[];
  view: View;
  selectedIds: ReadonlySet<number>;
  onSelectedIdsChange: (ids: ReadonlySet<number>) => void;
  onEditLayout: (id: number, view?: EditView) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onRestore: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
  onRename: (layout: LayoutSummary) => void;
  onAssign: (layout: LayoutSummary) => void;
  onPreview: (layout: LayoutSummary) => void;
  previewPending: boolean;
  sort: Sort;
  onSortChange: (sort: Sort) => void;
  sortDisabled: boolean;
}) {
  const SortIcon = sort === 'desc' ? ArrowDown : sort === 'asc' ? ArrowUp : ArrowUpDown;
  const selectedCount = items.filter((layout) => selectedIds.has(layout.id)).length;

  function onRowSelectedChange(id: number, selected: boolean) {
    const next = new Set(selectedIds);
    if (selected) {
      next.add(id);
    } else {
      next.delete(id);
    }
    onSelectedIdsChange(next);
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-10">
            <Checkbox
              aria-label={__('Select all layouts on this page', 'yaycheckout')}
              checked={selectedCount === items.length}
              onCheckedChange={() =>
                onSelectedIdsChange(new Set(selectedCount === items.length ? [] : items.map((layout) => layout.id)))
              }
            />
          </TableHead>
          <TableHead>{__('Name', 'yaycheckout')}</TableHead>
          {isPro && <TableHead>{__('Assignment', 'yaycheckout')}</TableHead>}
          <TableHead>{__('Status', 'yaycheckout')}</TableHead>
          <TableHead aria-sort={sort === 'desc' ? 'descending' : sort === 'asc' ? 'ascending' : 'none'}>
            <Button
              size="sm"
              variant="ghost"
              className="-ml-2.5 h-7"
              disabled={sortDisabled}
              onClick={() => onSortChange(sort === 'desc' ? 'asc' : 'desc')}
            >
              {__('Last updated', 'yaycheckout')}
              <SortIcon className={cn('size-3.5', !sort && 'text-muted-foreground')} />
            </Button>
          </TableHead>
          <TableHead>{__('Actions', 'yaycheckout')}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.map((layout) => (
          <LayoutTableRow
            key={layout.id}
            layout={layout}
            view={view}
            selected={selectedIds.has(layout.id)}
            onSelectedChange={(selected) => onRowSelectedChange(layout.id, selected)}
            onEditLayout={onEditLayout}
            onSetActive={onSetActive}
            onDeactivate={onDeactivate}
            onArchive={onArchive}
            onRestore={onRestore}
            onDelete={onDelete}
            onDuplicate={onDuplicate}
            onRename={onRename}
            onAssign={onAssign}
            onPreview={onPreview}
            previewPending={previewPending}
          />
        ))}
      </TableBody>
    </Table>
  );
}

function LayoutTableRow({
  layout,
  view,
  selected,
  onSelectedChange,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onRestore,
  onDelete,
  onDuplicate,
  onRename,
  onAssign,
  onPreview,
  previewPending,
}: {
  layout: LayoutSummary;
  view: View;
  selected: boolean;
  onSelectedChange: (selected: boolean) => void;
  onEditLayout: (id: number, view?: EditView) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onRestore: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
  onRename: (layout: LayoutSummary) => void;
  onAssign: (layout: LayoutSummary) => void;
  onPreview: (layout: LayoutSummary) => void;
  previewPending: boolean;
}) {
  return (
    <TableRow data-state={selected ? 'selected' : undefined}>
      <TableCell>
        <Checkbox
          aria-label={sprintf(
            /* translators: %s: layout title */
            __('Select "%s"', 'yaycheckout'),
            layout.title,
          )}
          checked={selected}
          onCheckedChange={(checked) => onSelectedChange(checked === true)}
        />
      </TableCell>
      <TableCell className="font-medium">
        {/* On the div, not the cell: auto table layout ignores a cell's max-width, so the title would widen the column. */}
        <div className="max-w-[320px] truncate" title={layout.title}>
          {layout.title}
        </div>
        {layout.hasThankYou && layout.status !== 'trash' && (
          <button
            type="button"
            className="text-muted-foreground hover:text-foreground text-xs font-normal underline-offset-2 hover:underline"
            onClick={() => onEditLayout(layout.id, 'thank-you')}
          >
            {__('Thank you', 'yaycheckout')}
          </button>
        )}
      </TableCell>
      {isPro && (
        <TableCell>
          <AssignmentChips rules={layout.rules} />
        </TableCell>
      )}
      <TableCell>
        <LayoutStatusBadge layout={layout} />
      </TableCell>
      <TableCell className="text-muted-foreground whitespace-nowrap">
        {formatDateAtTime(layout.updatedAt) || '—'}
      </TableCell>
      <TableCell>
        <LayoutRowActions
          layout={layout}
          view={view}
          onEditLayout={onEditLayout}
          onSetActive={onSetActive}
          onDeactivate={onDeactivate}
          onArchive={onArchive}
          onRestore={onRestore}
          onDelete={onDelete}
          onDuplicate={onDuplicate}
          onRename={onRename}
          onAssign={onAssign}
          onPreview={onPreview}
          previewPending={previewPending}
        />
      </TableCell>
    </TableRow>
  );
}

/** Per-row actions. Activate turns into Deactivate on an active row. Archive is disabled on the active row, so deactivating stays an explicit choice. Publishing is the editor's job. */
function LayoutRowActions({
  layout,
  view,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onRestore,
  onDelete,
  onDuplicate,
  onRename,
  onAssign,
  onPreview,
  previewPending,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number, view?: EditView) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onRestore: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
  onRename: (layout: LayoutSummary) => void;
  onAssign: (layout: LayoutSummary) => void;
  onPreview: (layout: LayoutSummary) => void;
  /** One link mints at a time; every Preview button waits. */
  previewPending: boolean;
}) {
  if (view === 'archived') {
    return (
      <div className="flex items-center gap-2">
        <Button size="sm" variant="outline" disabled={previewPending} onClick={() => onPreview(layout)}>
          {__('Preview', 'yaycheckout')}
        </Button>
        <Button size="sm" variant="outline" onClick={() => onRestore(layout)}>
          {__('Restore', 'yaycheckout')}
        </Button>
        <Button size="sm" variant="destructive-outline" onClick={() => onDelete(layout)}>
          {__('Delete for good', 'yaycheckout')}
        </Button>
      </div>
    );
  }

  return (
    <div className="flex items-center gap-2">
      <Button size="sm" variant="outline" onClick={() => onEditLayout(layout.id)}>
        {__('Edit', 'yaycheckout')}
      </Button>
      <Button size="sm" variant="outline" disabled={previewPending} onClick={() => onPreview(layout)}>
        {__('Preview', 'yaycheckout')}
      </Button>
      {/* One button, two states; the min width keeps the column aligned across rows. */}
      {layout.isActive ? (
        <Button size="sm" variant="destructive-outline" className="min-w-25" onClick={() => onDeactivate(layout)}>
          {__('Deactivate', 'yaycheckout')}
        </Button>
      ) : (
        <Button size="sm" variant="outline" className="min-w-25" onClick={() => onSetActive(layout)}>
          {__('Activate', 'yaycheckout')}
        </Button>
      )}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            size="icon-sm"
            variant="ghost"
            className="text-muted-foreground"
            aria-label={__('More actions', 'yaycheckout')}
          >
            <MoreHorizontal className="size-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onSelect={() => onAssign(layout)}>{__('Assignment', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem onSelect={() => onRename(layout)}>{__('Rename', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem onSelect={() => onDuplicate(layout)}>{__('Duplicate', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem variant="destructive" disabled={layout.isActive} onSelect={() => onArchive(layout)}>
            {__('Archive', 'yaycheckout')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

/** "Archived 3 layouts.", plus what was refused and why, from the first refusal's message. */
function toastBulkResult(action: LayoutBulkAction, { done, failed }: LayoutBulkResult) {
  const doneMessage = bulkDoneMessage(action, done.length);

  if (failed.length === 0) {
    toast.success(doneMessage);
    return;
  }

  const failedMessage = sprintf(
    /* translators: 1: number of layouts, 2: the reason for the first one */
    _n('%1$d layout was not changed: %2$s', '%1$d layouts were not changed: %2$s', failed.length, 'yaycheckout'),
    failed.length,
    failed[0].message,
  );

  if (done.length === 0) {
    toast.error(failedMessage);
  } else {
    toast.warning(`${doneMessage} ${failedMessage}`);
  }
}

function bulkDoneMessage(action: LayoutBulkAction, count: number): string {
  switch (action) {
    case 'deactivate':
      /* translators: %d: number of layouts */
      return sprintf(_n('Deactivated %d layout.', 'Deactivated %d layouts.', count, 'yaycheckout'), count);
    case 'archive':
      /* translators: %d: number of layouts */
      return sprintf(_n('Archived %d layout.', 'Archived %d layouts.', count, 'yaycheckout'), count);
    case 'restore':
      /* translators: %d: number of layouts */
      return sprintf(_n('Restored %d layout.', 'Restored %d layouts.', count, 'yaycheckout'), count);
    case 'delete':
      /* translators: %d: number of layouts */
      return sprintf(_n('Deleted %d layout.', 'Deleted %d layouts.', count, 'yaycheckout'), count);
  }
}

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
    <Card>
      <TableRowsSkeleton />
    </Card>
  );
}

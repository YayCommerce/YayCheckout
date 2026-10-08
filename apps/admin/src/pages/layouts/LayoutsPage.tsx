import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, Search } from 'lucide-react';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { useDebounceFn } from 'rooks';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { editLayoutIdFrom, withEditLayout } from '@/lib/helpers/layout.helper';
import { isPro } from '@/lib/plan';
import {
  LAYOUT_KEYS,
  useActivateLayoutMutation,
  useArchiveLayoutMutation,
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
  type LayoutCounts,
  type LayoutListResponse,
  type LayoutSummary,
  type PreviewLink,
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
import { AssignmentChips } from '@/pages/layouts/assignment/AssignmentChips';
import { AssignmentDrawer } from '@/pages/layouts/assignment/AssignmentDrawer';
import { DeactivateModal } from '@/pages/layouts/DeactivateModal';
import { DeleteModal } from '@/pages/layouts/DeleteModal';
import { LayoutStatusBadge } from '@/pages/layouts/LayoutStatusBadge';
import { NewLayoutModal } from '@/pages/layouts/new-layout/NewLayoutModal';
import { PreviewLinkModal } from '@/pages/layouts/PreviewLinkModal';
import { SetActiveModal } from '@/pages/layouts/SetActiveModal';
import PageLayout from '@/pages/PageLayout';

type View = 'listed' | 'archived';

const MIN_ITEMS_FOR_LIST_CONTROLS = 10;

export default function LayoutsPage() {
  const [view, setView] = useState<View>('listed');
  const [pageIndex, setPageIndex] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  // useDebounceFn has no cancel; the trailing callback checks this ref to drop a stale value.
  const searchInputRef = useRef('');

  // Page reset happens in the same update as the filter change: a separate
  // effect would first fetch a page that may not exist under the new filter.
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
    status: view === 'archived' ? ['trash'] : LISTED_LAYOUT_STATUSES,
    search,
    page: pageIndex + 1,
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
      heading={__('Layouts', 'yaycheckout')}
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
}) {
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const [newLayoutOpen, setNewLayoutOpen] = useState(false);
  // The URL is the only record of the open editor, so browser Back closes it and other screens can deep-link to it.
  const editingLayoutId = editLayoutIdFrom(searchParams);
  // The state marks an entry this page pushed, so closing pops it instead of leaving a duplicate behind.
  const openEditor = (id: number) =>
    setSearchParams(withEditLayout(searchParams, id), { state: { isEditorPushed: true } });
  const lastEditingLayoutId = useRef(editingLayoutId);
  const [activatingLayout, setActivatingLayout] = useState<LayoutSummary | null>(null);
  const [deactivatingLayout, setDeactivatingLayout] = useState<LayoutSummary | null>(null);
  const [archivingLayout, setArchivingLayout] = useState<LayoutSummary | null>(null);
  const [deletingLayout, setDeletingLayout] = useState<LayoutSummary | null>(null);
  const [assigningLayout, setAssigningLayout] = useState<LayoutSummary | null>(null);
  const [previewing, setPreviewing] = useState<{ layout: LayoutSummary; link: PreviewLink } | null>(null);

  const activateMutation = useActivateLayoutMutation();
  const deactivateMutation = useDeactivateLayoutMutation();
  const archiveMutation = useArchiveLayoutMutation();
  const restoreMutation = useRestoreLayoutMutation();
  const deleteMutation = useDeleteLayoutMutation();
  const duplicateMutation = useDuplicateLayoutMutation();
  const previewLinkMutation = useCreatePreviewLinkMutation();

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

  function onRestore(layout: LayoutSummary) {
    restoreMutation.mutate(layout.id, {
      onSuccess: () => {
        toast.success(__('Layout restored.', 'yaycheckout'));

        if (listData?.items.length === 1 && pageIndex > 0) {
          onPageIndexChange(pageIndex - 1);
        }
      },
    });
  }

  function onDelete(layout: LayoutSummary) {
    deleteMutation.mutate(layout.id, {
      onSuccess: () => {
        setDeletingLayout(null);
        toast.success(__('Layout deleted.', 'yaycheckout'));

        // The last row of a later page is gone; that page no longer exists.
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
    return url.toString();
  }, [editingLayoutId]);

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

  const showListControls = (view === 'archived' ? counts.archived : counts.listed) >= MIN_ITEMS_FOR_LIST_CONTROLS;
  // totalPages is 0 for an empty result.
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
      <AssignmentDrawer layout={assigningLayout} onOpenChange={(open) => !open && setAssigningLayout(null)} />
      <PreviewLinkModal
        layout={previewing?.layout ?? null}
        link={previewing?.link ?? null}
        onOpenChange={(open) => !open && setPreviewing(null)}
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
                      onEditLayout={openEditor}
                      onSetActive={setActivatingLayout}
                      onDeactivate={setDeactivatingLayout}
                      onArchive={setArchivingLayout}
                      onRestore={onRestore}
                      onDelete={setDeletingLayout}
                      onDuplicate={onDuplicate}
                      onAssign={setAssigningLayout}
                      onPreview={onPreview}
                      previewPending={previewLinkMutation.isPending}
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
          <Card>
            <CardHeader>
              <CardTitle>{__('Health', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <p className="text-muted-foreground text-sm">{__('Not available yet.', 'yaycheckout')}</p>
          </Card>

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
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onRestore,
  onDelete,
  onDuplicate,
  onAssign,
  onPreview,
  previewPending,
}: {
  items: LayoutSummary[];
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onRestore: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
  onAssign: (layout: LayoutSummary) => void;
  onPreview: (layout: LayoutSummary) => void;
  previewPending: boolean;
}) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{__('Name', 'yaycheckout')}</TableHead>
          {isPro && <TableHead>{__('Assignment', 'yaycheckout')}</TableHead>}
          <TableHead>{__('Status', 'yaycheckout')}</TableHead>
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
            onRestore={onRestore}
            onDelete={onDelete}
            onDuplicate={onDuplicate}
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
  onEditLayout,
  onSetActive,
  onDeactivate,
  onArchive,
  onRestore,
  onDelete,
  onDuplicate,
  onAssign,
  onPreview,
  previewPending,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onRestore: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
  onAssign: (layout: LayoutSummary) => void;
  onPreview: (layout: LayoutSummary) => void;
  previewPending: boolean;
}) {
  return (
    <TableRow>
      <TableCell className="max-w-[320px] truncate font-medium" title={layout.title}>
        {layout.title}
      </TableCell>
      {isPro && (
        <TableCell className="max-w-70">
          <AssignmentChips rules={layout.rules} />
        </TableCell>
      )}
      <TableCell>
        <LayoutStatusBadge layout={layout} />
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
          onAssign={onAssign}
          onPreview={onPreview}
          previewPending={previewPending}
        />
      </TableCell>
    </TableRow>
  );
}

/** Per-row actions. Archive is disabled on the active row, so deactivating stays an explicit choice. Publishing is the editor's job. */
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
  onAssign,
  onPreview,
  previewPending,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onArchive: (layout: LayoutSummary) => void;
  onRestore: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
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
      {/* Disabled rather than hidden so the action column keeps its shape. */}
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
          <DropdownMenuItem onSelect={() => onAssign(layout)}>{__('Assignment', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem onSelect={() => onDuplicate(layout)}>{__('Duplicate', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem variant="destructive" disabled={!layout.isActive} onSelect={() => onDeactivate(layout)}>
            {__('Deactivate', 'yaycheckout')}
          </DropdownMenuItem>
          <DropdownMenuItem disabled={layout.isActive} onSelect={() => onArchive(layout)}>
            {__('Archive', 'yaycheckout')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
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

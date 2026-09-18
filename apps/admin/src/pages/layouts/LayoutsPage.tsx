import { useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Plus, Search } from 'lucide-react';
import { useDebounceFn } from 'rooks';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { formatDateTime } from '@/lib/helpers/date.helper';
import {
  LAYOUT_KEYS,
  useActivateLayoutMutation,
  useDeactivateLayoutMutation,
  useDeleteLayoutMutation,
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
import { DeactivateModal } from '@/pages/layouts/DeactivateModal';
import { DeleteModal } from '@/pages/layouts/DeleteModal';
import { NewLayoutModal } from '@/pages/layouts/new-layout/NewLayoutModal';
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
      description={__('Create, publish and activate checkout layouts.', 'yaycheckout')}
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
  const [newLayoutOpen, setNewLayoutOpen] = useState(false);
  const [editingLayoutId, setEditingLayoutId] = useState<number | null>(null);
  const [activatingLayout, setActivatingLayout] = useState<LayoutSummary | null>(null);
  const [deactivatingLayout, setDeactivatingLayout] = useState<LayoutSummary | null>(null);
  const [deletingLayout, setDeletingLayout] = useState<LayoutSummary | null>(null);

  const activateMutation = useActivateLayoutMutation();
  const deactivateMutation = useDeactivateLayoutMutation();
  const deleteMutation = useDeleteLayoutMutation();
  const duplicateMutation = useDuplicateLayoutMutation();

  // Invalidate rather than patch: on Free, activating one row deactivates another.
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
      <DeleteModal
        layout={deletingLayout}
        onOpenChange={(open) => !open && setDeletingLayout(null)}
        onConfirm={onDelete}
        isPending={deleteMutation.isPending}
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
                      onEditLayout={setEditingLayoutId}
                      onSetActive={setActivatingLayout}
                      onDeactivate={setDeactivatingLayout}
                      onDelete={setDeletingLayout}
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
  onDelete,
  onDuplicate,
}: {
  items: LayoutSummary[];
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
}) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{__('Name', 'yaycheckout')}</TableHead>
          {/* TODO: Assignment column (Pro) once LayoutSummary carries an `assignment` field. */}
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
            onDelete={onDelete}
            onDuplicate={onDuplicate}
          />
        ))}
      </TableBody>
    </Table>
  );
}

function statusBadge(layout: LayoutSummary) {
  if (layout.status === 'trash') {
    return <Badge variant="destructive-soft">{__('Archived', 'yaycheckout')}</Badge>;
  }

  if (layout.status === 'publish') {
    return (
      <Badge variant="success">
        {layout.hasUnpublishedChanges
          ? __('Published · unpublished changes', 'yaycheckout')
          : __('Published', 'yaycheckout')}
      </Badge>
    );
  }

  if (layout.status === 'future') {
    return (
      <Badge variant="outline">
        {layout.scheduledAt
          ? sprintf(
              /* translators: %s: scheduled publish date */
              __('Scheduled · %s', 'yaycheckout'),
              formatDateTime(layout.scheduledAt),
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
  onDelete,
  onDuplicate,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
  onDuplicate: (layout: LayoutSummary) => void;
}) {
  return (
    <TableRow>
      <TableCell className="max-w-[320px] truncate font-medium" title={layout.title}>
        {layout.title}
      </TableCell>
      <TableCell>{statusBadge(layout)}</TableCell>
      <TableCell className="font-mono text-xs">
        {layout.isActive ? <Badge variant="success">{__('Active', 'yaycheckout')}</Badge> : '—'}
      </TableCell>
      <TableCell className="font-mono text-xs">
        {formatDateTime(layout.publishedAt) || __('never', 'yaycheckout')}
      </TableCell>
      <TableCell>
        <LayoutRowActions
          layout={layout}
          view={view}
          onEditLayout={onEditLayout}
          onSetActive={onSetActive}
          onDeactivate={onDeactivate}
          onDelete={onDelete}
          onDuplicate={onDuplicate}
        />
      </TableCell>
    </TableRow>
  );
}

/** Per-row actions: Edit, Set active, Delete for good and the "⋯" menu. Preview and Restore have no onClick yet; publishing is the editor's job. */
function LayoutRowActions({
  layout,
  view,
  onEditLayout,
  onSetActive,
  onDeactivate,
  onDelete,
  onDuplicate,
}: {
  layout: LayoutSummary;
  view: View;
  onEditLayout: (id: number) => void;
  onSetActive: (layout: LayoutSummary) => void;
  onDeactivate: (layout: LayoutSummary) => void;
  onDelete: (layout: LayoutSummary) => void;
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
        <Button size="sm" variant="destructive-outline" onClick={() => onDelete(layout)}>
          {__('Delete for good', 'yaycheckout')}
        </Button>
      </div>
    );
  }

  const canPublish = layout.hasUnpublishedChanges;

  return (
    <div className="flex items-center gap-2">
      <Button size="sm" variant="outline" onClick={() => onEditLayout(layout.id)}>
        {__('Edit', 'yaycheckout')}
      </Button>
      <Button size="sm" variant="outline">
        {canPublish ? __('Preview draft', 'yaycheckout') : __('Preview', 'yaycheckout')}
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
          <DropdownMenuItem onSelect={() => onDuplicate(layout)}>{__('Duplicate', 'yaycheckout')}</DropdownMenuItem>
          <DropdownMenuItem variant="destructive" disabled={!layout.isActive} onSelect={() => onDeactivate(layout)}>
            {__('Deactivate', 'yaycheckout')}
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

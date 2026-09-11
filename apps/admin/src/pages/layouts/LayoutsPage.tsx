import { Fragment, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { useLayoutsPageQuery } from '@/lib/queries/layout.queries';
import { LayoutRow, LayoutsPageData } from '@/lib/schema/layout.schema';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import PageLayout from '@/pages/PageLayout';

const CASES: { value: string; label: string }[] = [
  { value: 'free-first-draft', label: 'Free — first draft' },
  { value: 'free-one-active', label: 'Free — one active' },
  { value: 'free-active-unpublished-changes', label: 'Free — active, unpublished changes' },
  { value: 'free-limit-reached', label: 'Free — limit reached' },
  { value: 'safe-mode', label: 'Safe mode on' },
  { value: 'pro-with-rules', label: 'Pro — list with rules' },
  { value: 'edge-no-layouts-left', label: 'Edge — no layouts left' },
  { value: 'edge-archive-view', label: 'Edge — archive view' },
  { value: 'edge-many-long-names', label: 'Edge — many layouts, long names' },
  { value: 'edge-read-only-role', label: 'Edge — read-only role' },
  { value: 'edge-all-statuses', label: 'Edge — all statuses' },
];

/**
 * Renders every non-modal state from YC-02_Layouts_Home.dc.html against dump
 * data returned by GET /layouts?case=<key> — see LayoutsDumpData.php. No
 * button here calls a real mutation; every enabled/disabled state comes from
 * the payload. Modals (assignment preview, set active, publish, archive,
 * archive-blocked, delete) are a separate ticket.
 */
export default function LayoutsPage() {
  const [caseKey, setCaseKey] = useState(CASES[0].value);
  const { data, isPending, error } = useLayoutsPageQuery(caseKey);

  return (
    <PageLayout
      heading={__('Layouts', 'yaycheckout')}
      description={__('Create, publish and activate checkout layouts.', 'yaycheckout')}
      actions={
        <Select value={caseKey} onValueChange={setCaseKey}>
          <SelectTrigger className="w-64">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {CASES.map((c) => (
              <SelectItem key={c.value} value={c.value}>
                {c.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      }
    >
      {isPending && <LayoutsPageSkeleton />}
      {error && <p className="text-destructive text-sm">{error.message}</p>}
      {data && <LayoutsPageBody data={data} />}
    </PageLayout>
  );
}

function LayoutsPageBody({ data }: { data: LayoutsPageData }) {
  return (
    <div className="space-y-4">
      {data.banner && (
        <Alert destructive={data.banner.tone === 'warning'}>
          {data.banner.title && <AlertTitle>{data.banner.title}</AlertTitle>}
          <AlertDescription>
            <div className="flex items-center justify-between gap-4">
              <span>{data.banner.message}</span>
              {data.banner.action && (
                <Button size="sm" variant="outline">
                  {data.banner.action.label}
                </Button>
              )}
              {data.banner.link && (
                <Button size="sm" variant="link">
                  {data.banner.link.label}
                </Button>
              )}
            </div>
          </AlertDescription>
        </Alert>
      )}

      <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <h2 className="flex-1 text-lg font-semibold">{__('Your layouts', 'yaycheckout')}</h2>
            {data.search && (
              <Input
                defaultValue={data.search.term}
                placeholder={__('Search layouts…', 'yaycheckout')}
                className="w-52"
              />
            )}
            {data.planLimit.reached && <Badge variant="outline">PRO</Badge>}
            <Button size="sm" disabled={data.planLimit.reached}>
              {__('Add layout', 'yaycheckout')}
            </Button>
          </div>

          {data.archivedView && (
            <div className="flex">
              <Button size="sm" variant="outline" className="rounded-r-none">
                {__('Active list', 'yaycheckout')} ({data.counts.active})
              </Button>
              <Button size="sm" variant="primary" className="rounded-l-none">
                {__('Archive', 'yaycheckout')} ({data.counts.archived})
              </Button>
            </div>
          )}

          {data.planPromo && (
            <Card className="border-dashed">
              <div className="flex items-center gap-3">
                <Badge variant="outline">PRO</Badge>
                <p className="text-muted-foreground flex-1 text-sm">{data.planPromo.message}</p>
                <Button size="sm" variant="outline">
                  {__('Upgrade', 'yaycheckout')}
                </Button>
              </div>
            </Card>
          )}

          {data.emptyState ? (
            <Card>
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon" />
                  <EmptyTitle>{data.emptyState.title}</EmptyTitle>
                  <EmptyDescription>{data.emptyState.message}</EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                  <Button size="sm">{__('Add layout', 'yaycheckout')}</Button>
                  <Button size="sm" variant="outline">
                    {__('Restore from archive', 'yaycheckout')}
                  </Button>
                </EmptyContent>
              </Empty>
            </Card>
          ) : (
            <Card>
              <LayoutsTable data={data} />
              {(data.search || data.pagination) && (
                <div className="mt-3 flex items-center gap-4 border-t pt-3">
                  {data.search && (
                    <span className="text-muted-foreground flex-1 font-mono text-xs">
                      {__('showing', 'yaycheckout')} {data.search.totalShown} {__('of', 'yaycheckout')}{' '}
                      {data.search.total}
                    </span>
                  )}
                  {data.pagination && (
                    <div className="flex">
                      {Array.from({ length: data.pagination.totalPages }, (_, i) => i + 1).map((page) => (
                        <Button
                          key={page}
                          size="sm"
                          variant={page === data.pagination?.page ? 'primary' : 'outline'}
                          className="h-7 rounded-none px-2.5 text-xs first:rounded-l-md last:rounded-r-md"
                        >
                          {page}
                        </Button>
                      ))}
                    </div>
                  )}
                </div>
              )}
              {data.tableFooterNote && <p className="text-muted-foreground mt-3 text-xs">{data.tableFooterNote}</p>}
            </Card>
          )}
        </div>

        <aside className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{__('Health', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <div className="space-y-2">
              {data.health.rows.map((row) => (
                <div key={row.label} className="flex justify-between border-b pb-2 text-sm last:border-b-0 last:pb-0">
                  <span className="text-muted-foreground">{row.label}</span>
                  <span className="font-mono">{row.value}</span>
                </div>
              ))}
            </div>
            {data.health.note && <p className="text-muted-foreground mt-2 text-xs">{data.health.note}</p>}
            {data.health.link && (
              <Button size="sm" variant="link" className="mt-1 h-auto p-0">
                {data.health.link.label}
              </Button>
            )}
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{__('Compatibility', 'yaycheckout')}</CardTitle>
            </CardHeader>
            <p className={data.compatibility.tone === 'warning' ? 'text-destructive text-sm' : 'text-sm'}>
              {data.compatibility.summary}
            </p>
            {data.compatibility.link && (
              <Button size="sm" variant="link" className="mt-1 h-auto p-0">
                {data.compatibility.link.label}
              </Button>
            )}
          </Card>

          {data.asideNote && (
            <Card>
              <CardHeader>
                <CardTitle className="text-sm">{data.asideNote.title}</CardTitle>
              </CardHeader>
              <p className="text-muted-foreground text-sm">{data.asideNote.message}</p>
            </Card>
          )}
        </aside>
      </div>
    </div>
  );
}

function LayoutsTable({ data }: { data: LayoutsPageData }) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{__('Name', 'yaycheckout')}</TableHead>
          {data.table.showAssignmentColumn && <TableHead>{__('Assignment', 'yaycheckout')}</TableHead>}
          <TableHead>{__('Status', 'yaycheckout')}</TableHead>
          {data.table.showArchivedColumns ? (
            <TableHead>{__('Archived', 'yaycheckout')}</TableHead>
          ) : (
            <TableHead>{__('Active', 'yaycheckout')}</TableHead>
          )}
          <TableHead>{__('Last published', 'yaycheckout')}</TableHead>
          <TableHead>{__('Actions', 'yaycheckout')}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {data.layouts.map((row) => (
          <Fragment key={row.id}>
            <LayoutTableRow
              row={row}
              listLocked={data.listLocked}
              showAssignmentColumn={data.table.showAssignmentColumn}
            />
            {row.issue && (
              <TableRow>
                <TableCell
                  colSpan={data.table.showAssignmentColumn ? 6 : 5}
                  className="border-l-destructive bg-destructive/5 border-l-2 py-3 text-sm"
                >
                  <p>{row.issue.message}</p>
                  {row.issue.lastGoodRevision && (
                    <p className="text-muted-foreground mt-1 font-mono text-xs">
                      {__('last good revision:', 'yaycheckout')} {row.issue.lastGoodRevision}
                    </p>
                  )}
                </TableCell>
              </TableRow>
            )}
          </Fragment>
        ))}
      </TableBody>
    </Table>
  );
}

/** Green for anything live (Published*), red for a real problem or Archived, neutral otherwise. */
function statusBadgeVariant(row: LayoutRow) {
  if (row.statusTone === 'warning') return 'destructive';
  if (row.statusLabel === 'Archived') return 'destructive-soft';
  if (row.statusLabel.startsWith('Published')) return 'success';
  return 'outline';
}

function LayoutTableRow({
  row,
  listLocked,
  showAssignmentColumn,
}: {
  row: LayoutRow;
  listLocked: boolean;
  showAssignmentColumn: boolean;
}) {
  const dimmed = row.dimmed && !row.inFlight;

  return (
    <TableRow className={dimmed ? 'opacity-40' : undefined}>
      <TableCell className="max-w-[320px] truncate font-medium" title={row.title}>
        {row.title}
      </TableCell>
      {showAssignmentColumn && (
        <TableCell>
          {row.assignment ? (
            row.isActive ? (
              <span className="text-muted-foreground font-mono text-xs">{row.assignment}</span>
            ) : (
              <Badge variant="outline">{row.assignment}</Badge>
            )
          ) : (
            <span className="text-muted-foreground">—</span>
          )}
        </TableCell>
      )}
      <TableCell>
        {row.inFlight ? (
          <span className="text-muted-foreground flex items-center gap-2 text-xs">
            <span className="border-muted-foreground size-3 animate-spin rounded-full border-2 border-t-transparent" />
            {row.inFlight}
          </span>
        ) : (
          <Badge variant={statusBadgeVariant(row)}>{row.statusLabel}</Badge>
        )}
      </TableCell>
      <TableCell className="font-mono text-xs">
        {row.archivedAt ?? (row.isActive ? <Badge variant="secondary">{__('ACTIVE', 'yaycheckout')}</Badge> : '—')}
      </TableCell>
      <TableCell className="font-mono text-xs">{row.lastPublishedAt ?? __('never', 'yaycheckout')}</TableCell>
      <TableCell>
        <LayoutRowActions row={row} listLocked={listLocked} />
      </TableCell>
    </TableRow>
  );
}

function LayoutRowActions({ row, listLocked }: { row: LayoutRow; listLocked: boolean }) {
  if (listLocked) {
    return (
      <div className="flex items-center gap-2">
        <Button size="sm" variant="outline" disabled>
          {__('Edit', 'yaycheckout')}
        </Button>
        <Button size="sm" variant="outline" disabled>
          {__('Preview', 'yaycheckout')}
        </Button>
      </div>
    );
  }

  const { actions } = row;

  return (
    <div className="flex items-center gap-2">
      {actions.fixInEditor && <Button size="sm">{__('Fix in editor', 'yaycheckout')}</Button>}
      {actions.restoreLastGood && (
        <Button size="sm" variant="outline">
          {__('Restore last good', 'yaycheckout')}
        </Button>
      )}
      {actions.restore && (
        <Button size="sm" variant="outline">
          {__('Restore', 'yaycheckout')}
        </Button>
      )}
      {actions.edit !== 'hidden' && (
        <Button size="sm" variant="outline" disabled={actions.edit === 'disabled'}>
          {__('Edit', 'yaycheckout')}
        </Button>
      )}
      {actions.preview !== 'hidden' && (
        <Button size="sm" variant="outline" disabled={actions.preview === 'disabled'}>
          {actions.preview === 'previewDraft' ? __('Preview draft', 'yaycheckout') : __('Preview', 'yaycheckout')}
        </Button>
      )}
      {actions.publish && <Button size="sm">{__('Publish', 'yaycheckout')}</Button>}
      {!row.isActive && actions.setActive !== 'hidden' && (
        <Button size="sm" variant="outline" disabled={actions.setActive === 'disabled'}>
          {__('Set active', 'yaycheckout')}
        </Button>
      )}
      {row.isActive && <span className="text-muted-foreground text-xs">{__('active', 'yaycheckout')}</span>}
      {actions.deleteForGood && (
        <Button size="sm" variant="destructive-outline">
          {__('Delete for good', 'yaycheckout')}
        </Button>
      )}
      {actions.moreMenu && (
        <Button size="sm" variant="outline">
          ⋯
        </Button>
      )}
    </div>
  );
}

function LayoutsPageSkeleton() {
  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
      <Card>
        <div className="space-y-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-10 w-full" />
          ))}
        </div>
      </Card>
      <aside className="space-y-4">
        <Card>
          <Skeleton className="h-4 w-24" />
          <div className="mt-3 space-y-2">
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-2/3" />
          </div>
        </Card>
        <Card>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-3 h-3 w-1/2" />
        </Card>
      </aside>
    </div>
  );
}

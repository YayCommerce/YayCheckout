import { ChevronsUpDown } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import type { LayoutBulkAction, LayoutSummary } from '@/lib/schema/layout.schema';
import { BulkActionBox } from '@/components/ui/bulk-actions';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';

/**
 * The selection's Bulk Actions menu. On the Archive tab: Restore and Delete. Elsewhere: Deactivate
 * while any selected layout is active, acting on those only, and Archive only while none is,
 * matching the row menu and the server
 * (LayoutRepository::bulk() refuses to archive an active layout).
 */
export function LayoutBulkBar({
  selected,
  archived,
  onClear,
  onAction,
  disabled,
}: {
  selected: LayoutSummary[];
  /** The rows are on the Archive tab. */
  archived: boolean;
  onClear: () => void;
  onAction: (action: LayoutBulkAction) => void;
  disabled: boolean;
}) {
  const activeCount = selected.filter((layout) => layout.isActive).length;

  return (
    <BulkActionBox selected={selected.length} onClose={onClear}>
      <span className="text-sm whitespace-nowrap">
        {sprintf(
          /* translators: %d: number of selected layouts */
          __('%d selected', 'yaycheckout'),
          selected.length,
        )}
      </span>
      <Separator orientation="vertical" className="mx-1 h-5!" />
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button size="sm" variant="ghost" disabled={disabled}>
            {__('Bulk actions', 'yaycheckout')}
            <ChevronsUpDown className="size-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
          {archived ? (
            <>
              <DropdownMenuItem onSelect={() => onAction('restore')}>{__('Restore', 'yaycheckout')}</DropdownMenuItem>
              <DropdownMenuItem variant="destructive" onSelect={() => onAction('delete')}>
                {__('Delete', 'yaycheckout')}
              </DropdownMenuItem>
            </>
          ) : activeCount > 0 ? (
            <DropdownMenuItem variant="destructive" onSelect={() => onAction('deactivate')}>
              {activeCount < selected.length
                ? sprintf(
                    /* translators: %d: number of selected layouts that are active */
                    __('Deactivate (%d)', 'yaycheckout'),
                    activeCount,
                  )
                : __('Deactivate', 'yaycheckout')}
            </DropdownMenuItem>
          ) : (
            <DropdownMenuItem variant="destructive" onSelect={() => onAction('archive')}>
              {__('Archive', 'yaycheckout')}
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
    </BulkActionBox>
  );
}

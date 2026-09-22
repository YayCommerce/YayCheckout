import { ArrowRight } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type RowStatus = 'mapped' | 'custom' | 'needs-pro';

interface MappingRow {
  source: string;
  target: string;
  note?: string;
  status: RowStatus;
  muted?: boolean;
}

const ROWS: MappingRow[] = [
  { source: 'Section: Contact', target: 'Contact section', status: 'mapped' },
  { source: 'Section: Shipping', target: 'Shipping section', status: 'mapped' },
  { source: 'billing_first_name', target: 'First name field', status: 'mapped' },
  { source: 'billing_email', target: 'Email field', status: 'mapped' },
  { source: 'billing_vat', target: 'Extra field (text)', status: 'custom' },
  { source: 'delivery_note', target: 'Extra field (textarea)', status: 'custom' },
  { source: 'order_summary_position', target: 'Order summary block, right column', status: 'mapped' },
  {
    source: 'Multi-step config (2 steps)',
    target: 'Step blocks (Pro)',
    note: 'Your licence covers this. On Lite the layout would import as one page.',
    status: 'needs-pro',
  },
  { source: '+ 10 core address fields', target: 'mapped one-to-one', status: 'mapped', muted: true },
];

function StatusBadge({ status }: { status: RowStatus }) {
  switch (status) {
    case 'mapped':
      return <Badge variant="success-soft">{__('Mapped', 'yaycheckout')}</Badge>;
    case 'custom':
      return <Badge variant="dimmed">{__('Custom', 'yaycheckout')}</Badge>;
    case 'needs-pro':
      return <Badge variant="destructive-soft">{__('Needs Pro', 'yaycheckout')}</Badge>;
  }
}

export function MappingStep({ onBack, onNext }: { onBack: () => void; onNext: () => void }) {
  return (
    <>
      <div className="max-h-[60vh] overflow-auto rounded-lg border">
        <Table className="table-fixed">
          <TableHeader>
            <TableRow>
              <TableHead className="w-2/5 text-xs">{__('Source field', 'yaycheckout')}</TableHead>
              <TableHead className="w-8" />
              <TableHead className="text-xs">{__('YayCheckout block', 'yaycheckout')}</TableHead>
              <TableHead className="w-28 text-xs">{__('Status', 'yaycheckout')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {ROWS.map((row) => (
              <TableRow key={row.source} className={cn(row.muted && 'text-muted-foreground')}>
                <TableCell className="whitespace-normal">{row.source}</TableCell>
                <TableCell>
                  <ArrowRight className="text-muted-foreground size-3.5" />
                </TableCell>
                <TableCell className="whitespace-normal">
                  <div>{row.target}</div>
                  {row.note && <p className="text-destructive mt-1 text-xs">{row.note}</p>}
                </TableCell>
                <TableCell>
                  <StatusBadge status={row.status} />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <DialogFooter className="items-center border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-sm">
          {__('Every field lands as a movable block. You can rearrange everything after import.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onBack}>
            {__('Back', 'yaycheckout')}
          </Button>
          <Button onClick={onNext}>{__('Create draft', 'yaycheckout')}</Button>
        </div>
      </DialogFooter>
    </>
  );
}

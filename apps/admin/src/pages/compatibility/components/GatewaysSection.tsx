import { useRef, useState } from 'react';
import { ChevronDown, ChevronRight, ExternalLink } from 'lucide-react';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { copyToClipboard } from '@/lib/helpers/clipboard.helper';
import type { GatewayRow } from '@/lib/schema/compatibility.schema';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { StatusBadge } from './StatusBadge';

export function GatewaysSection({ gateways }: { gateways: GatewayRow[] }) {
  const blockOnly = gateways.filter((gateway) => gateway.status === 'block-only').length;
  const active = gateways.length - blockOnly;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Payment gateways', 'yaycheckout')}</CardTitle>
        <CardDescription>
          {sprintf(
            /* translators: 1: number of active gateways, 2: number of block-only gateways */
            __('%1$d active · %2$d block-only', 'yaycheckout'),
            active,
            blockOnly,
          )}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{__('Gateway', 'yaycheckout')}</TableHead>
              <TableHead>{__('Status', 'yaycheckout')}</TableHead>
              <TableHead>{__('Express pay', 'yaycheckout')}</TableHead>
              <TableHead>{__('Notes', 'yaycheckout')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {gateways.map((gateway) =>
              gateway.status === 'block-only' ? (
                <BlockOnlyRows key={gateway.id} gateway={gateway} />
              ) : (
                <TableRow key={gateway.id}>
                  <TableCell className="font-medium">{gateway.title}</TableCell>
                  <TableCell>
                    <StatusBadge status={gateway.status} />
                  </TableCell>
                  <TableCell>{gateway.expressPay ?? '—'}</TableCell>
                  <TableCell className="text-muted-foreground whitespace-normal">{gateway.notes ?? ''}</TableCell>
                </TableRow>
              ),
            )}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}

function vendorRequest(title: string): string {
  return sprintf(
    /* translators: %s: gateway title */
    __(
      'Hi — our WooCommerce store uses YayCheckout, which renders the classic checkout form. %s currently supports only the Checkout block, so it does not appear at our checkout. Could you add classic checkout support (a WC_Payment_Gateway with payment_fields())? Thanks.',
      'yaycheckout',
    ),
    title,
  );
}

/** The gateway row plus a "Why?" expander: explanation, docs link and a canned vendor request. */
function BlockOnlyRows({ gateway }: { gateway: GatewayRow }) {
  const [open, setOpen] = useState(false);
  const request = vendorRequest(gateway.title);

  const textareaRef = useRef<HTMLTextAreaElement>(null);

  async function onCopy() {
    if (await copyToClipboard(request, textareaRef.current)) {
      toast.success(__('Vendor request copied.', 'yaycheckout'));
    } else {
      toast.info(__('Press Ctrl+C / ⌘C to copy the request.', 'yaycheckout'));
    }
  }

  return (
    <>
      <TableRow>
        <TableCell className="font-medium">{gateway.title}</TableCell>
        <TableCell>
          <StatusBadge status={gateway.status} />
        </TableCell>
        <TableCell>{gateway.expressPay ?? '—'}</TableCell>
        <TableCell className="whitespace-normal">
          <Button
            variant="link"
            size="sm"
            className="h-auto p-0"
            aria-expanded={open}
            aria-controls={`why-${gateway.id}`}
            onClick={() => setOpen((value) => !value)}
          >
            {open ? <ChevronDown /> : <ChevronRight />}
            {__('Why?', 'yaycheckout')}
          </Button>
        </TableCell>
      </TableRow>
      {open && (
        <TableRow id={`why-${gateway.id}`} className="hover:bg-transparent">
          <TableCell colSpan={4} className="whitespace-normal">
            <div className="max-w-2xl space-y-3 py-1 text-sm">
              <p className="text-muted-foreground">
                {gateway.notes ??
                  __(
                    'This gateway registers itself only for the WooCommerce Checkout block. YayCheckout renders the classic checkout form, so shoppers will not see it until the vendor adds classic support.',
                    'yaycheckout',
                  )}
              </p>
              <Textarea
                ref={textareaRef}
                aria-label={__('Vendor request', 'yaycheckout')}
                readOnly
                rows={4}
                value={request}
                onFocus={(e) => e.target.select()}
              />
              <div className="flex flex-wrap gap-2">
                <Button size="sm" onClick={onCopy}>
                  {__('Copy vendor request', 'yaycheckout')}
                </Button>
                {gateway.docsUrl && (
                  <Button variant="outline" size="sm" asChild>
                    <a href={gateway.docsUrl} target="_blank" rel="noreferrer">
                      {__('See supported alternatives', 'yaycheckout')}
                      <ExternalLink />
                    </a>
                  </Button>
                )}
              </div>
            </div>
          </TableCell>
        </TableRow>
      )}
    </>
  );
}

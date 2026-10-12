import { ExternalLink } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { formatRelative } from '@/lib/helpers/date.helper';
import type { LayoutSummary, PreviewLink } from '@/lib/schema/layout.schema';
import { useCopyWithFallback } from '@/hooks/useCopyWithFallback';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/** Shows a freshly minted preview URL with Copy and Open. Closes to null; every open mints a new link. */
export function PreviewLinkModal({
  layout,
  link,
  onOpenChange,
}: {
  layout: LayoutSummary | null;
  link: PreviewLink | null;
  onOpenChange: (open: boolean) => void;
}) {
  const { fieldRef: inputRef, copy } = useCopyWithFallback<HTMLInputElement>(
    __('Link copied.', 'yaycheckout'),
    __('Press Ctrl+C / ⌘C to copy the link.', 'yaycheckout'),
  );

  function onCopy() {
    if (link) void copy(link.url);
  }

  return (
    <Dialog open={null !== layout && null !== link} onOpenChange={onOpenChange}>
      <DialogContent>
        {layout && link && (
          <>
            <DialogHeader>
              <DialogTitle>
                {sprintf(
                  /* translators: %s: layout title */
                  __('Preview "%s"', 'yaycheckout'),
                  layout.title,
                )}
              </DialogTitle>
            </DialogHeader>

            <p className="text-muted-foreground text-sm">
              {__(
                'Anyone with this link sees the layout on your checkout page with a sample cart. Orders cannot be placed from it.',
                'yaycheckout',
              )}
            </p>

            <Input ref={inputRef} readOnly value={link.url} onFocus={(e) => e.target.select()} />

            <p className="text-muted-foreground text-xs">
              {sprintf(
                /* translators: %s: relative time, e.g. "in an hour" */
                __('Expires %s.', 'yaycheckout'),
                formatRelative(link.expiresAt),
              )}
            </p>

            <DialogFooter>
              <Button variant="outline" asChild>
                <a href={link.url} target="_blank" rel="noreferrer">
                  {__('Open', 'yaycheckout')}
                  <ExternalLink className="size-3.5" />
                </a>
              </Button>
              <Button onClick={onCopy}>{__('Copy link', 'yaycheckout')}</Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

import { useEffect, useState, type RefObject } from 'react';
import {
  isEditorViewMessage,
  type EditorViewMessage,
  type LayoutView,
  type SwitchViewMessage,
} from '@blocks-shared/editor-messages';
import { CircleCheck, Plus, ShoppingCart, type LucideIcon } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';

type Page = 'main' | 'thank-you';

const PAGES: { page: Page; label: string; icon: LucideIcon }[] = [
  { page: 'main', label: __('Checkout', 'yaycheckout'), icon: ShoppingCart },
  { page: 'thank-you', label: __('Thank you', 'yaycheckout'), icon: CircleCheck },
];

/**
 * The layout's pages beside the editor: Checkout and Thank you, or Add thank-you page while the layout has none.
 * The editor in the iframe says which view is open on each load; a box asks it to switch (layout-view.tsx),
 * so its save-first prompt and the add-thank-you request stay in one place. Boxes wait for the first message.
 */
export function EditorViewNav({ frameRef }: { frameRef: RefObject<HTMLIFrameElement | null> }) {
  const [state, setState] = useState<Pick<EditorViewMessage, 'current' | 'hasThankYou'> | null>(null);

  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      if (
        event.origin === window.location.origin &&
        event.source === frameRef.current?.contentWindow &&
        isEditorViewMessage(event.data)
      ) {
        setState({ current: event.data.current, hasThankYou: event.data.hasThankYou });
      }
    };

    window.addEventListener('message', onMessage);

    return () => window.removeEventListener('message', onMessage);
  }, [frameRef]);

  const open = (view: LayoutView) => {
    const message: SwitchViewMessage = { source: 'yaycheckout-manager', type: 'switch-view', view };
    frameRef.current?.contentWindow?.postMessage(message, window.location.origin);
  };

  // Logged out is a variant of the checkout, so it keeps the Checkout box selected.
  const activePage: Page | null = state ? (state.current === 'thank-you' ? 'thank-you' : 'main') : null;

  return (
    <nav aria-label={__('Layout pages', 'yaycheckout')} className="flex w-52 shrink-0 flex-col gap-2 border-r p-3">
      {PAGES.map(({ page, label, icon: Icon }) => {
        if (page === 'thank-you' && state && !state.hasThankYou && activePage !== 'thank-you') {
          return (
            <button
              key={page}
              type="button"
              className="text-muted-foreground hover:text-foreground hover:border-foreground/40 flex items-center gap-2 rounded-md border border-dashed px-3 py-2.5 text-left text-sm transition-colors"
              onClick={() => open('thank-you')}
            >
              <Plus className="size-4 shrink-0" aria-hidden />
              {__('Add thank-you page', 'yaycheckout')}
            </button>
          );
        }

        const active = activePage === page;

        return (
          <button
            key={page}
            type="button"
            aria-current={active ? 'page' : undefined}
            disabled={null === state}
            className={cn(
              'flex items-center gap-2 rounded-md border px-3 py-2.5 text-left text-sm font-medium transition-colors disabled:opacity-50',
              active ? 'border-primary bg-primary/5 text-foreground' : 'hover:bg-muted text-muted-foreground',
            )}
            onClick={() => !active && open(page)}
          >
            <Icon className="size-4 shrink-0" aria-hidden />
            {label}
          </button>
        );
      })}
    </nav>
  );
}

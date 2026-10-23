import { useEffect, useState, type RefObject } from 'react';
import {
  isEditorViewMessage,
  type EditorViewMessage,
  type LayoutView,
  type SwitchViewMessage,
} from '@blocks-shared/editor-messages';
import { ChevronsLeft, ChevronsRight, CircleCheck, Plus, ShoppingCart, type LucideIcon } from 'lucide-react';
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
 * Collapsed, it is a rail of icon-only boxes; the toggle at its foot switches between the two.
 */
export function EditorViewNav({
  frameRef,
  collapsed,
  onToggle,
}: {
  frameRef: RefObject<HTMLIFrameElement | null>;
  collapsed: boolean;
  onToggle: () => void;
}) {
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
    <nav
      aria-label={__('Layout pages', 'yaycheckout')}
      className={cn('flex shrink-0 flex-col gap-2 border-r p-3', collapsed ? 'w-16' : 'w-52')}
    >
      {PAGES.map(({ page, label, icon: Icon }) => {
        if (page === 'thank-you' && state && !state.hasThankYou && activePage !== 'thank-you') {
          return (
            <button
              key={page}
              type="button"
              title={collapsed ? __('Add thank-you page', 'yaycheckout') : undefined}
              aria-label={collapsed ? __('Add thank-you page', 'yaycheckout') : undefined}
              className={cn(
                'text-muted-foreground hover:text-foreground hover:border-foreground/40 flex items-center gap-2 rounded-md border border-dashed py-2.5 text-left text-sm transition-colors',
                collapsed ? 'justify-center px-0' : 'px-3',
              )}
              onClick={() => open('thank-you')}
            >
              <Plus className="size-4 shrink-0" aria-hidden />
              {!collapsed && __('Add thank-you page', 'yaycheckout')}
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
            title={collapsed ? label : undefined}
            aria-label={collapsed ? label : undefined}
            className={cn(
              'flex items-center gap-2 rounded-md border py-2.5 text-left text-sm font-medium transition-colors disabled:opacity-50',
              collapsed ? 'justify-center px-0' : 'px-3',
              active ? 'border-primary bg-primary/5 text-foreground' : 'hover:bg-muted text-muted-foreground',
            )}
            onClick={() => !active && open(page)}
          >
            <Icon className="size-4 shrink-0" aria-hidden />
            {!collapsed && label}
          </button>
        );
      })}
      <button
        type="button"
        aria-expanded={!collapsed}
        title={collapsed ? __('Expand', 'yaycheckout') : undefined}
        aria-label={collapsed ? __('Expand', 'yaycheckout') : undefined}
        className={cn(
          'text-muted-foreground hover:bg-muted hover:text-foreground mt-auto flex items-center gap-2 rounded-md py-2 text-sm transition-colors',
          collapsed ? 'justify-center px-0' : 'px-3',
        )}
        onClick={onToggle}
      >
        {collapsed ? (
          <ChevronsRight className="size-4 shrink-0" aria-hidden />
        ) : (
          <>
            <ChevronsLeft className="size-4 shrink-0" aria-hidden />
            {__('Collapse', 'yaycheckout')}
          </>
        )}
      </button>
    </nav>
  );
}

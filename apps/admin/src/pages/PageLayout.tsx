import { ReactNode } from 'react';

/**
 * Shared frame for every route: a page header (heading + description on the
 * left, actions on the right, vertically centred on the heading) above the
 * page content. Keeps heading style, action placement and the content
 * container width consistent across pages.
 *
 * `actions` takes whatever each page needs — one button, a button group, a
 * plan badge — styled by the page; PageLayout only owns the slot. heading and
 * description are plain props. The content area stacks its direct children
 * with a consistent gap, so a page that lays out several sections gets even
 * spacing without setting it itself. No `aside` prop yet — Layouts lays out
 * its Health/Compatibility rail inside its own grid; lift it here once a
 * second screen needs a rail.
 */
export default function PageLayout({
  heading,
  description,
  actions,
  children,
}: {
  heading: string;
  description?: string;
  /** Right-aligned slot in the header, e.g. an "Add layout" or "Save" button. */
  actions?: ReactNode;
  children?: ReactNode;
}) {
  return (
    <div className="mx-auto max-w-7xl p-6">
      <div className="flex items-center justify-between gap-4">
        <div className="space-y-1">
          <h1 className="text-lg font-semibold">{heading}</h1>
          {description ? <p className="text-muted-foreground text-sm">{description}</p> : null}
        </div>
        {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
      </div>

      {children ? <div className="mt-6 space-y-6">{children}</div> : null}
    </div>
  );
}

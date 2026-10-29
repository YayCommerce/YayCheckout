import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';

import type { Template } from '@/lib/schema/template.schema';
import { cn } from '@/lib/utils';

/** Mirrors PAGE_WIDTH in scripts/capture-template-previews.py: the iframe renders at it and is scaled down to the frame. */
const PAGE_WIDTH = 1280;

/** The frame's width to height. */
const FRAME_ASPECT = 4 / 3;

/** The page height the frame shows at once. */
const FRAME_PAGE_HEIGHT = PAGE_WIDTH / FRAME_ASPECT;

/** Used until the preview reports its height, and kept when it can't. */
const FALLBACK_PAGE_HEIGHT = FRAME_PAGE_HEIGHT;

/**
 * A template's mock page, scaled into a 4:3 frame, that scrolls to its bottom while the enclosing
 * `group` card is hovered or focused. Falls back to `placeholder`, or the template's name, when there is no preview.
 * `children` overlay the frame, such as a badge on its corner. `previewUrl` shows another of the
 * template's pages, such as its thank-you page; it defaults to the checkout's.
 *
 * The sandbox allows same-origin but not scripts: we can read the page's height, and nothing in the
 * preview can run.
 */
export function TemplatePreview({
  template,
  previewUrl = template.previewUrl,
  placeholder,
  children,
}: {
  template: Template;
  previewUrl?: string;
  placeholder?: ReactNode;
  children?: ReactNode;
}) {
  const frameRef = useRef<HTMLDivElement>(null);
  const [frameWidth, setFrameWidth] = useState(0);
  const [pageHeight, setPageHeight] = useState(FALLBACK_PAGE_HEIGHT);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const element = frameRef.current;
    if (!element) return;

    const observer = new ResizeObserver(([entry]) => {
      setFrameWidth(entry.contentRect.width);
    });
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  const scale = frameWidth / PAGE_WIDTH;
  const shift = Math.max(0, pageHeight - FRAME_PAGE_HEIGHT) * scale;
  const duration = Math.min(6, Math.max(2, (pageHeight / 1000) * 1.5));

  function onLoad(event: React.SyntheticEvent<HTMLIFrameElement>) {
    const height = event.currentTarget.contentDocument?.documentElement.scrollHeight ?? 0;
    if (height > 0) setPageHeight(height);
  }

  const showPreview = previewUrl !== '' && !failed;

  // The border sits on the outer box, so the measured inner box is exactly 4:3 and a one-screen page fits it.
  return (
    <div className="relative w-full overflow-hidden rounded-sm border">
      <div
        ref={frameRef}
        className="bg-muted w-full overflow-hidden"
        style={
          {
            aspectRatio: FRAME_ASPECT,
            '--preview-shift': `${shift}px`,
            '--preview-duration': `${duration}s`,
          } as CSSProperties
        }
      >
        {showPreview ? (
          <div
            className={cn(
              'transition-transform duration-400 ease-in-out motion-reduce:transition-none',
              'motion-safe:group-hover:-translate-y-(--preview-shift) motion-safe:group-hover:duration-(--preview-duration)',
              'motion-safe:group-focus-visible:-translate-y-(--preview-shift) motion-safe:group-focus-visible:duration-(--preview-duration)',
            )}
          >
            <iframe
              src={previewUrl}
              title={template.name}
              sandbox="allow-same-origin"
              loading="lazy"
              tabIndex={-1}
              aria-hidden="true"
              onLoad={onLoad}
              onError={() => setFailed(true)}
              className="pointer-events-none origin-top-left border-0"
              style={{ width: PAGE_WIDTH, height: pageHeight, transform: `scale(${scale})` }}
            />
          </div>
        ) : (
          <div className="text-muted-foreground flex size-full items-center justify-center p-2 text-center text-xs">
            {placeholder ?? template.name}
          </div>
        )}
      </div>
      {children}
    </div>
  );
}

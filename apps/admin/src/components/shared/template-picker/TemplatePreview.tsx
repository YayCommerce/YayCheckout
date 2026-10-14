import { useEffect, useRef, useState, type CSSProperties } from 'react';

import type { Template } from '@/lib/schema/template.schema';
import { cn } from '@/lib/utils';

/** The width the previews were captured at; the iframe renders at it and is scaled down to the frame. */
const PAGE_WIDTH = 1280;

/** Used until the preview reports its height, and kept when it can't. */
const FALLBACK_PAGE_HEIGHT = 960;

/**
 * A template's mock page, scaled into a 4:3 frame, that scrolls to its bottom while the enclosing
 * `group` card is hovered or focused. Falls back to the template's name when there is no preview.
 *
 * The sandbox allows same-origin but not scripts: we can read the page's height, and nothing in the
 * preview can run.
 */
export function TemplatePreview({ template }: { template: Template }) {
  const frameRef = useRef<HTMLDivElement>(null);
  const [frame, setFrame] = useState({ width: 0, height: 0 });
  const [pageHeight, setPageHeight] = useState(FALLBACK_PAGE_HEIGHT);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const element = frameRef.current;
    if (!element) return;

    const observer = new ResizeObserver(([entry]) => {
      setFrame({ width: entry.contentRect.width, height: entry.contentRect.height });
    });
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  const scale = frame.width / PAGE_WIDTH;
  // Floored, so a one-screen page whose scaled height rounds past the frame doesn't jiggle on hover.
  const shift = Math.max(0, Math.floor(pageHeight * scale - frame.height));
  const duration = Math.min(6, Math.max(2, (pageHeight / 1000) * 1.5));

  function onLoad(event: React.SyntheticEvent<HTMLIFrameElement>) {
    const height = event.currentTarget.contentDocument?.documentElement.scrollHeight ?? 0;
    if (height > 0) setPageHeight(height);
  }

  const showPreview = template.previewUrl !== '' && !failed;

  return (
    <div
      ref={frameRef}
      className="bg-muted relative aspect-4/3 w-full overflow-hidden rounded-sm border"
      style={{ '--preview-shift': `${shift}px`, '--preview-duration': `${duration}s` } as CSSProperties}
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
            src={template.previewUrl}
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
          {template.name}
        </div>
      )}
    </div>
  );
}

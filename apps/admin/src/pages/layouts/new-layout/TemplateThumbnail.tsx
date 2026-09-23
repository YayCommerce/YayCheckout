import { cn } from '@/lib/utils';
import { FALLBACK, SKETCHES } from '@/pages/layouts/new-layout/template-thumbnail-sketches';

/** Layout sketch for a template slug, or a generic form when the slug is unknown.
 * The PHP catalogue is the source of truth for slugs; an unknown one must not blank the card. */
export function TemplateThumbnail({ slug, className }: { slug: string; className?: string }) {
  return (
    <svg viewBox="0 0 120 80" aria-hidden="true" className={cn('bg-muted size-full', className)}>
      {Object.hasOwn(SKETCHES, slug) ? SKETCHES[slug] : FALLBACK}
    </svg>
  );
}

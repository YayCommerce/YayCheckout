import { api } from '@/lib/api/api';
import {
  Layout,
  layoutCountsResponseSchema,
  LayoutListResponse,
  layoutListResponseWireSchema,
  LayoutStatus,
  LayoutSummary,
  LayoutSummaryWire,
  LayoutWire,
  layoutWireSchema,
} from '@/lib/schema/layout.schema';

/*
 * The only place the wire's snake_case meets the app's camelCase — see
 * docs/code-standards.md (REST). Responses are parsed against the strict wire
 * schemas, then converted; request bodies are converted on the way out.
 */

function summaryFromWire(wire: LayoutSummaryWire): LayoutSummary {
  return {
    id: wire.id,
    title: wire.title,
    status: wire.status,
    hasUnpublishedChanges: wire.has_unpublished_changes,
    isActive: wire.is_active,
    publishedAt: wire.published_at,
    scheduledAt: wire.scheduled_at,
  };
}

function layoutFromWire(wire: LayoutWire): Layout {
  return {
    ...summaryFromWire(wire),
    draft: wire.draft,
    snapshot: wire.snapshot,
    publishedRevision: wire.published_revision,
    derived: wire.derived,
  };
}

function parseLayout(payload: unknown): Layout {
  return layoutFromWire(layoutWireSchema.parse(payload));
}

export type LayoutListParams = {
  status?: LayoutStatus[];
  search?: string;
  page?: number;
};

export async function getLayouts(params: LayoutListParams = {}): Promise<LayoutListResponse> {
  const searchParams: Record<string, string> = {};
  if (params.status?.length) searchParams.status = params.status.join(',');
  if (params.search) searchParams.search = params.search;
  if (params.page != null) searchParams.page = String(params.page);

  const wire = layoutListResponseWireSchema.parse(
    await api.get('layouts', Object.keys(searchParams).length ? { searchParams } : undefined).json(),
  );

  return {
    items: wire.items.map(summaryFromWire),
    total: wire.total,
    page: wire.page,
    totalPages: wire.total_pages,
  };
}

export async function getLayoutCounts() {
  return layoutCountsResponseSchema.parse(await api.get('layouts/counts').json()).counts;
}

export async function getLayout(id: number): Promise<Layout> {
  return parseLayout(await api.get(`layouts/${id}`).json());
}

export async function publishLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/publish`).json());
}

export type CreateLayoutDraftPayload =
  { name: string; source: 'blank' } | { name: string; source: 'library'; templateSlug: string };

export async function createLayoutDraft(payload: CreateLayoutDraftPayload): Promise<Layout> {
  // `template_slug` is the wire spelling — see LayoutRestController's create args.
  const json =
    payload.source === 'library'
      ? { name: payload.name, source: payload.source, template_slug: payload.templateSlug }
      : { name: payload.name, source: payload.source };

  return parseLayout(await api.post('layouts', { json }).json());
}

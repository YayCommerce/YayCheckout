import { api } from '@/lib/api/api';
import {
  Layout,
  layoutCountsResponseSchema,
  LayoutListResponse,
  layoutListResponseWireSchema,
  layoutRulesWireSchema,
  LayoutStatus,
  LayoutSummary,
  LayoutSummaryWire,
  LayoutWire,
  layoutWireSchema,
  PreviewLink,
  previewLinkWireSchema,
  type LayoutRulesResponse,
  type LayoutRulesWire,
} from '@/lib/schema/layout.schema';

function summaryFromWire(wire: LayoutSummaryWire): LayoutSummary {
  return {
    id: wire.id,
    title: wire.title,
    status: wire.status,
    isActive: wire.is_active,
    blocks: wire.blocks,
    size: wire.size,
    publishedAt: wire.published_at,
    scheduledAt: wire.scheduled_at,
    rules: wire.rules,
  };
}

function layoutFromWire(wire: LayoutWire): Layout {
  return {
    ...summaryFromWire(wire),
    content: wire.content,
  };
}

export function parseLayout(payload: unknown): Layout {
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

export async function activateLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/activate`).json());
}

export async function deactivateLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/deactivate`).json());
}

export async function archiveLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/archive`).json());
}

export async function restoreLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/restore`).json());
}

export async function duplicateLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/duplicate`).json());
}

export async function renameLayout(id: number, name: string): Promise<Layout> {
  return parseLayout(await api.put(`layouts/${id}`, { json: { name } }).json());
}

export async function resetLayout(id: number): Promise<Layout> {
  return parseLayout(await api.post(`layouts/${id}/reset`).json());
}

export async function createPreviewLink(id: number): Promise<PreviewLink> {
  const wire = previewLinkWireSchema.parse(await api.post(`layouts/${id}/preview-link`).json());

  return { url: wire.url, expiresAt: wire.expires_at };
}

export async function deleteLayout(id: number): Promise<void> {
  await api.delete(`layouts/${id}`);
}

export type CreateLayoutDraftPayload =
  { name: string; source: 'blank' } | { name: string; source: 'library'; templateSlug: string };

export async function createLayoutDraft(payload: CreateLayoutDraftPayload): Promise<Layout> {
  const json =
    payload.source === 'library'
      ? { name: payload.name, source: payload.source, template_slug: payload.templateSlug }
      : { name: payload.name, source: payload.source };

  return parseLayout(await api.post('layouts', { json }).json());
}

export async function getLayoutRules(id: number): Promise<LayoutRulesResponse> {
  return layoutRulesWireSchema.parse(await api.get(`layouts/${id}/rules`).json());
}

/** Replaces the rules; resolves to them as stored (list values de-duplicated). */
export async function saveLayoutRules({
  id,
  rules,
}: {
  id: number;
  rules: LayoutRulesWire;
}): Promise<LayoutRulesResponse> {
  return layoutRulesWireSchema.parse(await api.put(`layouts/${id}/rules`, { json: { rules } }).json());
}

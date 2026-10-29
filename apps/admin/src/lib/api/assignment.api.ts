import { api } from '@/lib/api/api';
import {
  assignmentOptionsWireSchema,
  type AssignmentOption,
  type AssignmentOptionKind,
} from '@/lib/schema/assignment.schema';

/** At most this many ids per lookup, as the route allows. */
const INCLUDE_LIMIT = 100;

/** Up to 20 matches; an empty search lists the first ones. */
export async function searchAssignmentOptions(kind: AssignmentOptionKind, search: string): Promise<AssignmentOption[]> {
  const wire = assignmentOptionsWireSchema.parse(
    await api.get(`assignment/${kind}`, search ? { searchParams: { search } } : undefined).json(),
  );

  return wire.options;
}

/** The labels of picked ids; an id the store lacks is absent from the reply. */
export async function getAssignmentOptionLabels(
  kind: AssignmentOptionKind,
  ids: number[],
): Promise<AssignmentOption[]> {
  const pages: number[][] = [];
  for (let start = 0; start < ids.length; start += INCLUDE_LIMIT) pages.push(ids.slice(start, start + INCLUDE_LIMIT));

  const replies = await Promise.all(
    pages.map(async (page) =>
      assignmentOptionsWireSchema.parse(
        await api.get(`assignment/${kind}`, { searchParams: { include: page.join(',') } }).json(),
      ),
    ),
  );

  return replies.flatMap((reply) => reply.options);
}

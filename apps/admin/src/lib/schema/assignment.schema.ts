import { z } from 'zod';

/** Mirrors AssignmentOptionsRestController's routes: what a cart condition's picker can list. */
export const assignmentOptionKindSchema = z.enum(['products', 'categories']);

export type AssignmentOptionKind = z.infer<typeof assignmentOptionKindSchema>;

/** Mirrors AssignmentOptionsRestController::products() and categories(). */
export const assignmentOptionsWireSchema = z
  .object({
    options: z.array(z.object({ id: z.number(), label: z.string() }).strict()),
  })
  .strict();

export type AssignmentOption = z.infer<typeof assignmentOptionsWireSchema>['options'][number];

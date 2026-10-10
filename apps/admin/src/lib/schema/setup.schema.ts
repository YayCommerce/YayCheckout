import { z } from 'zod';

import { layoutWireSchema, type Layout } from '@/lib/schema/layout.schema';

/** Mirrors SetupRestController::complete_setup(). */
export const setupResponseWireSchema = z
  .object({
    layout: layoutWireSchema,
  })
  .strict();

export type SetupResult = {
  layout: Layout;
};

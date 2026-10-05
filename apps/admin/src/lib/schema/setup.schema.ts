import { z } from 'zod';

import { designResponseWireSchema, type DesignResponse } from '@/lib/schema/design.schema';
import { layoutWireSchema, type Layout } from '@/lib/schema/layout.schema';

/** Mirrors SetupRestController::complete_setup(). */
export const setupResponseWireSchema = z
  .object({
    layout: layoutWireSchema,
    design: designResponseWireSchema,
  })
  .strict();

export type SetupResult = {
  layout: Layout;
  design: DesignResponse;
};

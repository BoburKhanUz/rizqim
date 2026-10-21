import type { SectionMessages } from './define';
import { management } from './management';
import { menu } from './menu';
import { orders } from './orders';
import { reports } from './reports';

// Every section of texts. A new area adds its file here (one import, one entry, one line in the type below).
export const SECTIONS = { orders, menu, management, reports } as const;

export type AllSectionMessages = SectionMessages<typeof orders> &
  SectionMessages<typeof menu> &
  SectionMessages<typeof management> &
  SectionMessages<typeof reports>;

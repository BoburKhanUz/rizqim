import { bookings } from './bookings';
import type { SectionMessages } from './define';
import { fiscal } from './fiscal';
import { floor } from './floor';
import { management } from './management';
import { menu } from './menu';
import { orders } from './orders';
import { permissions } from './permissions';
import { possetup } from './possetup';
import { reports } from './reports';
import { shifts } from './shifts';

// Every section of texts. A new area adds its file here (one import, one entry, one line in the type below).
export const SECTIONS = { orders, menu, management, reports, fiscal, floor, possetup, bookings, shifts, permissions } as const;

export type AllSectionMessages = SectionMessages<typeof orders> &
  SectionMessages<typeof menu> &
  SectionMessages<typeof management> &
  SectionMessages<typeof reports> &
  SectionMessages<typeof fiscal> &
  SectionMessages<typeof floor> &
  SectionMessages<typeof possetup> &
  SectionMessages<typeof bookings> &
  SectionMessages<typeof shifts> &
  SectionMessages<typeof permissions>;

import { audit } from './audit';
import { billing } from './billing';
import { bookings } from './bookings';
import { dailymenu } from './dailymenu';
import type { SectionMessages } from './define';
import { fiscal } from './fiscal';
import { format } from './format';
import { fiscalterminal } from './fiscalterminal';
import { floor } from './floor';
import { management } from './management';
import { menu } from './menu';
import { orders } from './orders';
import { permissions } from './permissions';
import { possetup } from './possetup';
import { reports } from './reports';
import { shifts } from './shifts';

// Every section of texts. A new area adds its file here (one import, one entry, one line in the type below).
export const SECTIONS = { orders, menu, management, reports, fiscal, fiscalterminal, floor, possetup, bookings, shifts, permissions, billing, audit, format, dailymenu } as const;

export type AllSectionMessages = SectionMessages<typeof orders> &
  SectionMessages<typeof menu> &
  SectionMessages<typeof management> &
  SectionMessages<typeof reports> &
  SectionMessages<typeof fiscal> &
  SectionMessages<typeof fiscalterminal> &
  SectionMessages<typeof floor> &
  SectionMessages<typeof possetup> &
  SectionMessages<typeof bookings> &
  SectionMessages<typeof shifts> &
  SectionMessages<typeof permissions> &
  SectionMessages<typeof billing> &
  SectionMessages<typeof audit> &
  SectionMessages<typeof format> &
  SectionMessages<typeof dailymenu>;

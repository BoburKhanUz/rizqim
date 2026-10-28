import { audit } from './audit';
import { auth } from './auth';
import type { SectionMessages } from './define';
import { monitoring } from './monitoring';
import { orders } from './orders';
import { roles } from './roles';
import { sessions } from './sessions';
import { team } from './team';
import { today } from './today';

// Every section of texts. A new area adds its file here (one import, one entry, one line in the type below).
export const SECTIONS = { auth, team, roles, audit, sessions, today, monitoring, orders } as const;

export type AllSectionMessages = SectionMessages<typeof auth> &
  SectionMessages<typeof team> &
  SectionMessages<typeof roles> &
  SectionMessages<typeof audit> &
  SectionMessages<typeof sessions> &
  SectionMessages<typeof today> &
  SectionMessages<typeof monitoring> &
  SectionMessages<typeof orders>;

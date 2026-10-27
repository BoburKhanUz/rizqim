import { audit } from './audit';
import { auth } from './auth';
import type { SectionMessages } from './define';
import { roles } from './roles';
import { sessions } from './sessions';
import { team } from './team';

// Every section of texts. A new area adds its file here (one import, one entry, one line in the type below).
export const SECTIONS = { auth, team, roles, audit, sessions } as const;

export type AllSectionMessages = SectionMessages<typeof auth> &
  SectionMessages<typeof team> &
  SectionMessages<typeof roles> &
  SectionMessages<typeof audit> &
  SectionMessages<typeof sessions>;

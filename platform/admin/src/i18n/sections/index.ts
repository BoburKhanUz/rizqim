import { audit } from './audit';
import { auth } from './auth';
import { customers } from './customers';
import type { SectionMessages } from './define';
import { finance } from './finance';
import { integrations } from './integrations';
import { marketing } from './marketing';
import { metrics } from './metrics';
import { monitoring } from './monitoring';
import { orders } from './orders';
import { pos } from './pos';
import { roles } from './roles';
import { sessions } from './sessions';
import { stores } from './stores';
import { system } from './system';
import { team } from './team';
import { today } from './today';

// Every section of texts. A new area adds its file here (one import, one entry, one line in the type below).
export const SECTIONS = { auth, team, roles, audit, sessions, today, monitoring, orders, stores, pos, finance, marketing, metrics, customers, system, integrations } as const;

export type AllSectionMessages = SectionMessages<typeof auth> &
  SectionMessages<typeof team> &
  SectionMessages<typeof roles> &
  SectionMessages<typeof audit> &
  SectionMessages<typeof sessions> &
  SectionMessages<typeof today> &
  SectionMessages<typeof monitoring> &
  SectionMessages<typeof orders> &
  SectionMessages<typeof stores> &
  SectionMessages<typeof pos> &
  SectionMessages<typeof finance> &
  SectionMessages<typeof marketing> &
  SectionMessages<typeof metrics> &
  SectionMessages<typeof customers> &
  SectionMessages<typeof system> &
  SectionMessages<typeof integrations>;

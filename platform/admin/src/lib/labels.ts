import { isPlatformRole, type PlatformRole } from './api/types';

// The six team roles of docs/PHASE4_PLAN.md section 5, in plain Uzbek. A role the app does not know shows as the backend sent it.
export const ROLE_LABELS: Record<PlatformRole, string> = {
  superadmin: 'Superadmin',
  operator: 'Operator',
  support: 'Yordam',
  finance: 'Moliya',
  sales: 'Savdo / ulash',
  viewer: "Faqat ko'rish",
};

export const roleLabel = (role: string | null | undefined): string => (role && isPlatformRole(role) ? ROLE_LABELS[role] : (role ?? ''));

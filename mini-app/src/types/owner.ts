import type { ApiUser } from './user';

// A manager or courier of the owner's store (GET /owner/employees).
export type Employee = ApiUser & { role: 'manager' | 'courier' };

// POST /owner/employees/invite
export interface EmployeeInvite {
  employee: Employee;
  invite_link: string;
}

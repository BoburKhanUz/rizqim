// Role -> screen decisions. The app has one bundle for every role, so these are the single place to look.

// Customers are signed-in users that are not staff. 'user' is a legacy value the backend no longer sends.
export function isCustomerRole(role: string | null | undefined): boolean {
  return !role || role === 'customer' || role === 'user';
}

// Where "/" sends a staff member. Customers (and unknown roles) stay on the catalog.
export function getRoleHomePath(role: string | null | undefined): string | null {
  switch (role) {
    case 'courier':
      return '/courier';
    case 'owner':
      return '/admin/analytics';
    case 'manager':
      return '/manager';
    default:
      return null;
  }
}

// Query keys of the staff features. The orders keys are prefixes on purpose: ['orders'] reaches every list (a mutation
// invalidates them all), ['orders', 'active'] the open lists, ['orders', 'history'] the finished ones. `view` names the
// screen a list belongs to ("manager", "courier", "board"), so each screen has its own cache entry.
export const staffKeys = {
  orders: ['orders'] as const,
  allActive: ['orders', 'active'] as const,
  allHistory: ['orders', 'history'] as const,
  active: (view: string) => ['orders', 'active', view] as const,
  history: (view: string) => ['orders', 'history', view] as const,
  couriers: ['storeCouriers'] as const,

  products: ['owner', 'products'] as const,
  categories: ['owner', 'categories'] as const,
  stopList: (branchId?: number) => ['owner', 'stop-list', branchId ?? null] as const,
  allStopList: ['owner', 'stop-list'] as const,
  batches: (branchId?: number, includeClosed = false) => ['owner', 'batches', branchId ?? null, includeClosed] as const,
  allBatches: ['owner', 'batches'] as const,
  menuAvailability: (storeId: number | string) => ['menu-availability', String(storeId)] as const,

  employees: ['owner', 'employees'] as const,
  permissions: ['me', 'permissions'] as const,

  settings: ['owner-settings'] as const,

  finance: ['ownerFinance'] as const,
  subscription: ['owner', 'subscription'] as const,
  invoices: (query?: object) => ['owner', 'invoices', query ?? null] as const,
  entitlements: ['owner', 'billing', 'entitlements'] as const,

  analytics: ['owner', 'analytics'] as const,
  analyticsPeriod: (period: string, from?: string | null, to?: string | null) => ['owner', 'analytics', period, from ?? null, to ?? null] as const,

};

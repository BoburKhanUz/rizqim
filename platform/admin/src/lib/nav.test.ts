import { describe, expect, it } from 'vitest';
import { canOpen, hasPermission, homePath, NAV_ITEMS, sectionOf, SYSTEM_KEYS, visibleNav } from './nav';

describe('nav', () => {
  it('lists the sections in the order of the plan, "Tizim" last', () => {
    expect(NAV_ITEMS.map((item) => item.key)).toEqual(['today', 'orders', 'stores', 'pos', 'finance', 'customers', 'marketing', 'monitoring', 'integrations', 'team', 'roles', 'audit', 'sessions']);
    expect(SYSTEM_KEYS).toEqual(['team', 'roles', 'audit', 'sessions']);
  });

  it('reads a permission with or without the admin. prefix', () => {
    expect(hasPermission(['admin.team.manage'], 'team.manage')).toBe(true);
    expect(hasPermission(['team.manage'], 'admin.team.manage')).toBe(true);
    expect(hasPermission(['admin.team.manage'], 'team.manage.extra')).toBe(false);
  });

  it('hides what the member may not open and keeps Sessiyalarim for everyone', () => {
    expect(visibleNav([]).map((item) => item.key)).toEqual(['sessions']);
    const operator = visibleNav(['admin.today.view', 'admin.orders.view', 'admin.stores.view', 'admin.monitoring.view']).map((item) => item.key);
    expect(operator).toEqual(['today', 'orders', 'stores', 'monitoring', 'sessions']);
    expect(visibleNav(['admin.team.manage']).map((item) => item.key)).toEqual(['team', 'roles', 'sessions']);
  });

  it('opens POS for devices.view and Mijozlar for either users.view or support.view', () => {
    const pos = NAV_ITEMS.find((item) => item.key === 'pos')!;
    expect(canOpen(pos, ['admin.devices.view'])).toBe(true);
    expect(canOpen(pos, ['admin.stores.view'])).toBe(false);
    const customers = NAV_ITEMS.find((item) => item.key === 'customers')!;
    expect(canOpen(customers, ['admin.support.view'])).toBe(true);
    expect(canOpen(customers, ['admin.users.view'])).toBe(true);
  });

  it('finds the section of a path, including the search targets', () => {
    expect(sectionOf('/orders/9f1c')?.key).toBe('orders');
    expect(sectionOf('/stores/12')?.key).toBe('stores');
    expect(sectionOf('/users/7')?.key).toBe('customers');
    expect(sectionOf('/team/3')?.key).toBe('team');
    expect(sectionOf('/nowhere')).toBeUndefined();
  });

  it('lands on the first section the member has, else Sessiyalarim', () => {
    expect(homePath(['admin.orders.view', 'admin.stores.view'])).toBe('/orders');
    expect(homePath([])).toBe('/sessions');
  });
});

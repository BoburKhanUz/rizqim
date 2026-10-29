import { describe, expect, it } from 'vitest';
import { hasAny, homePath, sectionOf, visibleNav } from './permissions';

const OWNER = [
  'orders.view', 'orders.manage', 'orders.cancel', 'menu.view', 'menu.manage', 'stoplist.manage', 'batches.manage', 'staff.view',
  'branches.manage', 'zones.manage', 'settings.manage', 'reports.view', 'finance.view',
  'tables.manage', 'bookings.manage', 'customer_accounts.manage', 'pos.close_shift', 'permissions.manage', 'audit.view',
];

describe('visibleNav', () => {
  it('shows every section to an owner, zones included', () => {
    expect(visibleNav(OWNER).map((item) => item.key)).toEqual([
      'dashboard', 'orders', 'bookings', 'floor', 'menu', 'stoplist', 'batches', 'dailymenu', 'yield', 'shifts', 'accounts', 'staff', 'branches', 'zones', 'settings', 'possetup', 'permissions', 'audit', 'fiscal', 'reports', 'finance', 'billing',
    ]);
  });

  it('hides what the permissions do not open (a manager has no zones, settings or finance)', () => {
    const manager = ['orders.view', 'menu.view', 'stoplist.manage', 'batches.manage', 'staff.view', 'branches.manage', 'reports.view'];
    expect(visibleNav(manager).map((item) => item.key)).toEqual(['dashboard', 'orders', 'menu', 'stoplist', 'batches', 'dailymenu', 'yield', 'shifts', 'staff', 'branches', 'fiscal', 'reports']);
  });

  it('gives a manager the POS sections their permissions open (bookings, tables, nasiya, shifts), not the settings ones', () => {
    const manager = ['orders.view', 'menu.view', 'reports.view', 'tables.manage', 'bookings.manage', 'customer_accounts.manage', 'pos.close_shift', 'branches.manage'];
    const keys = visibleNav(manager).map((item) => item.key);
    expect(keys).toEqual(['dashboard', 'orders', 'bookings', 'floor', 'menu', 'yield', 'shifts', 'accounts', 'branches', 'fiscal', 'reports']);
    expect(keys).not.toContain('possetup');
  });

  it('keeps the fiscal section from people who only read the menu (a cook, a waiter)', () => {
    expect(visibleNav(['orders.view', 'menu.view', 'kitchen.manage']).map((item) => item.key)).toEqual(['dashboard', 'orders', 'menu']);
    expect(visibleNav(['menu.manage']).map((item) => item.key)).toEqual(['menu', 'dailymenu', 'fiscal']);
  });

  it('opens a section with any one of its permissions', () => {
    expect(visibleNav(['batches.manage']).map((item) => item.key)).toEqual(['batches']); // a cook: the kazan, not the stop-list
    expect(visibleNav(['orders.view']).map((item) => item.key)).toEqual(['dashboard', 'orders']);
    expect(visibleNav(['settings.manage']).map((item) => item.key)).toEqual(['branches', 'settings', 'possetup', 'fiscal']);
    expect(visibleNav(['permissions.manage']).map((item) => item.key)).toEqual(['permissions']); // only the owner holds it
    expect(visibleNav(['bookings.manage']).map((item) => item.key)).toEqual(['bookings']);
    expect(visibleNav(['audit.view']).map((item) => item.key)).toEqual(['audit']); // P4-32: the store's audit log
    expect(visibleNav(['pos.close_shift']).map((item) => item.key)).toEqual(['shifts']);
    // P6-15: the manager who runs the day sets the daily menu; the yield report is a report
    expect(visibleNav(['stoplist.manage']).map((item) => item.key)).toEqual(['stoplist', 'dailymenu']);
    expect(visibleNav(['reports.view']).map((item) => item.key)).toEqual(['dashboard', 'yield', 'shifts', 'fiscal', 'reports']);
    expect(hasAny(['a'], ['b', 'a'])).toBe(true);
    expect(hasAny([], ['a'])).toBe(false);
  });

  it('shows nothing without permissions, and lands on the first section the person has', () => {
    expect(visibleNav([])).toEqual([]);
    expect(homePath([])).toBeNull();
    expect(homePath(['menu.view', 'orders.view'])).toBe('/dashboard');
    expect(homePath(['menu.view'])).toBe('/menu');
  });

  it('finds the section of a nested path', () => {
    expect(sectionOf('/zones')?.key).toBe('zones');
    expect(sectionOf('/orders/abc')?.key).toBe('orders');
    expect(sectionOf('/ordersx')).toBeUndefined();
    expect(sectionOf('/stoplist/batches')?.key).toBe('batches');
    expect(sectionOf('/stoplist')?.key).toBe('stoplist');
    expect(sectionOf('/pos-setup')?.key).toBe('possetup');
    expect(sectionOf('/permissions')?.key).toBe('permissions');
    expect(sectionOf('/fiscal')?.key).toBe('fiscal');
    expect(sectionOf('/floor')?.key).toBe('floor');
    expect(sectionOf('/daily-menu')?.key).toBe('dailymenu');
    expect(sectionOf('/yield')?.key).toBe('yield');
  });
});

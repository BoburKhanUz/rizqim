import { describe, expect, it } from 'vitest';
import { getNavItems, getRootPaths, ROOT_PATHS } from './navItems';

describe('the tabs of a manager', () => {
  it('are the orders, the stop-list and the profile', () => {
    expect(getNavItems('manager').map((item) => item.to)).toEqual(['/manager', '/admin/stoplist', '/profile']);
  });

  it('keep the owner\'s as they were: the stop-list is a page he opens from the menu', () => {
    expect(getNavItems('owner').map((item) => item.to)).not.toContain('/admin/stoplist');
  });
});

describe('getRootPaths', () => {
  it('is without a way back for the tabs of the role, and with one for what is not a tab of it', () => {
    expect(getRootPaths('manager').has('/admin/stoplist')).toBe(true);
    expect(getRootPaths('owner').has('/admin/stoplist')).toBe(false); // the owner reaches it from the menu: Telegram\'s back button shows
    expect(getRootPaths('owner').has('/admin/analytics')).toBe(true);
    expect(getRootPaths('owner').has('/manager/orders/abc')).toBe(false);
    expect(getRootPaths('courier').has('/courier')).toBe(true);
    expect(getRootPaths('manager').has('/success')).toBe(true);
  });

  it('gives a customer the shop\'s tabs', () => {
    expect(getRootPaths('customer').has('/cart')).toBe(true);
    expect(getRootPaths(null).has('/orders')).toBe(true);
  });

  it('is a subset of the paths that are a tab of anybody', () => {
    for (const role of ['owner', 'manager', 'courier', 'customer'] as const) {
      for (const path of getRootPaths(role)) expect(ROOT_PATHS.has(path)).toBe(true);
    }
  });
});

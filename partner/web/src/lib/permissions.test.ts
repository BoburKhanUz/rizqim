import { describe, expect, it } from 'vitest';
import { homePath, visibleNav } from './permissions';

describe('visibleNav', () => {
  it('shows every section to an owner', () => {
    const owner = ['orders.view', 'menu.view', 'stoplist.manage', 'staff.view', 'reports.view', 'finance.view', 'settings.manage'];
    expect(visibleNav(owner).map((item) => item.key)).toEqual(['dashboard', 'orders', 'menu', 'stoplist', 'branches', 'staff', 'reports', 'finance', 'settings']);
  });

  it('hides what the permissions do not open (a manager has no finance, settings or menu editing)', () => {
    const manager = ['orders.view', 'menu.view', 'stoplist.manage', 'staff.view', 'reports.view'];
    expect(visibleNav(manager).map((item) => item.key)).toEqual(['dashboard', 'orders', 'menu', 'stoplist', 'staff', 'reports']);
  });

  it('shows nothing without permissions, and lands on the first section the person has', () => {
    expect(visibleNav([])).toEqual([]);
    expect(homePath([])).toBeNull();
    expect(homePath(['orders.view', 'menu.view'])).toBe('/orders');
  });
});

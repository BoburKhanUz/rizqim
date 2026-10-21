import { describe, expect, it } from 'vitest';
import { hasAny, homePath, sectionOf, visibleNav } from './permissions';

const OWNER = [
  'orders.view', 'orders.manage', 'orders.cancel', 'menu.view', 'menu.manage', 'stoplist.manage', 'batches.manage', 'staff.view',
  'branches.manage', 'zones.manage', 'settings.manage', 'reports.view', 'finance.view',
];

describe('visibleNav', () => {
  it('shows every section to an owner, zones included', () => {
    expect(visibleNav(OWNER).map((item) => item.key)).toEqual([
      'dashboard', 'orders', 'menu', 'stoplist', 'batches', 'staff', 'branches', 'zones', 'settings', 'reports', 'finance',
    ]);
  });

  it('hides what the permissions do not open (a manager has no zones, settings or finance)', () => {
    const manager = ['orders.view', 'menu.view', 'stoplist.manage', 'batches.manage', 'staff.view', 'branches.manage', 'reports.view'];
    expect(visibleNav(manager).map((item) => item.key)).toEqual(['dashboard', 'orders', 'menu', 'stoplist', 'batches', 'staff', 'branches', 'reports']);
  });

  it('opens a section with any one of its permissions', () => {
    expect(visibleNav(['batches.manage']).map((item) => item.key)).toEqual(['batches']); // a cook: the kazan, not the stop-list
    expect(visibleNav(['orders.view']).map((item) => item.key)).toEqual(['dashboard', 'orders']);
    expect(visibleNav(['settings.manage']).map((item) => item.key)).toEqual(['branches', 'settings']);
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
  });
});

import { describe, expect, it } from 'vitest';
import { getRoleHomePath, isCustomerRole } from './roles';

describe('getRoleHomePath', () => {
  it.each([
    ['owner', '/admin/analytics'],
    ['manager', '/manager'],
    ['courier', '/courier'],
  ])('sends %s to %s', (role, path) => {
    expect(getRoleHomePath(role)).toBe(path);
  });

  it('keeps customers (and unknown roles) on the catalog', () => {
    expect(getRoleHomePath('customer')).toBeNull();
    expect(getRoleHomePath('user')).toBeNull();
    // P4-25: superadmins sign in through the control center; the Mini App has no screen for the role
    expect(getRoleHomePath('super_admin')).toBeNull();
    expect(getRoleHomePath('superadmin')).toBeNull();
    expect(getRoleHomePath(undefined)).toBeNull();
    expect(getRoleHomePath(null)).toBeNull();
  });
});

describe('isCustomerRole', () => {
  it('is true for customers, the legacy "user" role and a missing role', () => {
    expect(isCustomerRole('customer')).toBe(true);
    expect(isCustomerRole('user')).toBe(true);
    expect(isCustomerRole(undefined)).toBe(true);
    expect(isCustomerRole(null)).toBe(true);
    expect(isCustomerRole('')).toBe(true);
  });

  it('is false for every staff role', () => {
    for (const role of ['owner', 'manager', 'courier']) {
      expect(isCustomerRole(role)).toBe(false);
    }
  });

  it('agrees with getRoleHomePath: a customer is exactly a role without a staff home', () => {
    for (const role of ['owner', 'manager', 'courier', 'customer', undefined]) {
      expect(isCustomerRole(role)).toBe(getRoleHomePath(role) === null);
    }
  });
});

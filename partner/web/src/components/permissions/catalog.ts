import { isMessageKey, type MessageKey, type Translate } from '@/i18n';

// The permissions of the "Huquqlar" page, grouped the way an owner thinks about them (docs/AUTH.md section 6). The server
// owns the list (GET /owner/permissions): a permission it lists that is not here is shown under "Boshqa" with the server's
// own label, and one listed here but not by the server is not shown.
export const PERMISSION_GROUPS = [
  { id: 'orders', keys: ['orders.view', 'orders.manage', 'orders.cancel', 'orders.discount', 'orders.void_item', 'orders.assign_courier'] },
  { id: 'pos', keys: ['pos.open_shift', 'pos.close_shift', 'pos.take_payment', 'pos.cash_in_out', 'pos.reprint_bill', 'pos.refund'] },
  { id: 'floor', keys: ['tables.manage', 'kitchen.manage', 'stoplist.manage', 'batches.manage', 'deliveries.manage'] },
  { id: 'menu', keys: ['menu.view', 'menu.manage'] },
  { id: 'team', keys: ['staff.view', 'staff.pin', 'devices.view', 'devices.manage'] },
  { id: 'reports', keys: ['reports.view', 'finance.view'] },
  { id: 'venue', keys: ['branches.manage', 'bookings.manage', 'customer_accounts.manage'] },
  // The owner keeps these: they cannot be given to another role (Permission::isGrantable), and show only where a role has one
  { id: 'owner', keys: ['orders.refund', 'finance.manage', 'settings.manage', 'zones.manage', 'staff.manage', 'audit.view', 'permissions.manage'] },
] as const;

export type GroupId = (typeof PERMISSION_GROUPS)[number]['id'] | 'other';

// What moves money or cancels things: giving it needs a second look, and the row says so.
export const RISKY_PERMISSIONS: ReadonlySet<string> = new Set([
  'orders.cancel',
  'orders.discount',
  'orders.void_item',
  'orders.refund',
  'pos.refund',
  'pos.cash_in_out',
  'finance.manage',
]);

export const isRisky = (key: string): boolean => RISKY_PERMISSIONS.has(key);

const textKey = (key: string, suffix = '') => `perm_${key.replace(/\./g, '_')}${suffix}`;

// A permission's short name and its one-line explanation in the page's language; a permission without words here shows
// the label the server sent.
export function permissionWords(t: Translate, key: string, serverLabel: string): { label: string; hint: string | null } {
  const label = textKey(key);
  const hint = textKey(key, '_hint');
  return {
    label: isMessageKey(label) ? t(label as MessageKey) : serverLabel,
    hint: isMessageKey(hint) ? t(hint as MessageKey) : null,
  };
}

export interface ListedPermission {
  key: string;
  label: string;
  grantable: boolean;
}

export interface PermissionSection {
  id: GroupId;
  rows: ListedPermission[];
}

// The server's permissions in the page's groups. A row shows when the role may be given it (grantable) or already has it
// (so an owner-only permission a role somehow holds can be taken away); nothing else is worth a switch that cannot be turned.
export function sectionsOf(listed: readonly ListedPermission[], visible: (permission: ListedPermission) => boolean): PermissionSection[] {
  const byKey = new Map(listed.map((permission) => [permission.key, permission]));
  const known = new Set<string>(PERMISSION_GROUPS.flatMap((group) => group.keys));
  const sections: PermissionSection[] = PERMISSION_GROUPS.map((group) => ({
    id: group.id,
    rows: group.keys.flatMap((key) => {
      const permission = byKey.get(key);
      return permission && visible(permission) ? [permission] : [];
    }),
  }));
  sections.push({ id: 'other', rows: listed.filter((permission) => !known.has(permission.key) && visible(permission)) });
  return sections.filter((section) => section.rows.length > 0);
}

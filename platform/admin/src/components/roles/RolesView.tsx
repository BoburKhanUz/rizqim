'use client';

import { Fragment, useMemo, useState } from 'react';
import { Lock, ShieldAlert } from 'lucide-react';
import { Card, CardSkeletons, ConfirmDialog, EmptyState, FormError, InlineError, Notice, PageHeader, Switch } from '@rizqim/ui';
import { useRoleMatrix, useSetRolePermission } from '@/lib/api/hooks';
import type { PermissionRow, RoleColumn, RoleMatrix } from '@/lib/api/types';
import { errorText } from '@/lib/errors';
import { roleLabel } from '@/lib/labels';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';

// What moves money or reaches wide: giving it to a role asks first (the name without the "admin." prefix)
const RISKY = new Set(['payouts.approve', 'finance.manage', 'orders.refund_approve', 'orders.intervene', 'stores.impersonate', 'system.manage', 'team.manage', 'pii.view', 'export']);
const isRisky = (key: string) => RISKY.has(key.replace(/^admin\./, ''));

const SUPERADMIN = 'superadmin';

interface Pending {
  role: RoleColumn;
  permission: PermissionRow;
}

// Tizim → Rollar (team.manage): the matrix of what each team role may do, like the partner web's "Huquqlar". The superadmin
// column is locked (it can do everything and the backend keeps it that way). A switch is one PUT /admin/roles, saved at once,
// audited and confirmed with a fresh 2FA code by the step-up dialog.
export function RolesView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('team.manage');
  const matrix = useRoleMatrix(allowed);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_roles')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  return (
    <>
      <PageHeader title={t('roles_title')} subtitle={t('roles_subtitle')} />
      {matrix.isError ? (
        <InlineError title={t('roles_loadError')} message={errorText(matrix.error, t)} retryLabel={t('retry')} retrying={matrix.isFetching} onRetry={() => void matrix.refetch()} />
      ) : !matrix.isSuccess ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : (
        <Matrix matrix={matrix.data} />
      )}
    </>
  );
}

function Matrix({ matrix }: { matrix: RoleMatrix }) {
  const t = useT();
  const save = useSetRolePermission();
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [asking, setAsking] = useState<Pending | null>(null);

  const groups = useMemo(() => {
    const order: string[] = [];
    const rows = new Map<string, PermissionRow[]>();
    for (const permission of matrix.permissions) {
      const group = permission.group || '';
      if (!rows.has(group)) {
        rows.set(group, []);
        order.push(group);
      }
      rows.get(group)?.push(permission);
    }
    return order.map((group) => ({ group, rows: rows.get(group) ?? [] }));
  }, [matrix.permissions]);

  const granted = (role: RoleColumn, permission: PermissionRow) => role.key === SUPERADMIN || (matrix.grants[role.key] ?? []).includes(permission.key);

  const apply = (role: RoleColumn, permission: PermissionRow, allowedNow: boolean) => {
    setNotice(null);
    setError(null);
    save.mutate(
      { role: role.key, permission: permission.key, allowed: allowedNow },
      {
        onSuccess: () => {
          setAsking(null);
          setNotice(t(allowedNow ? 'roles_granted' : 'roles_revoked', { role: role.label || roleLabel(role.key), permission: permission.label }));
        },
        onError: (failure) => {
          setAsking(null);
          setError(errorText(failure, t));
        },
      },
    );
  };

  const toggle = (role: RoleColumn, permission: PermissionRow, wanted: boolean) => {
    if (wanted && isRisky(permission.key)) setAsking({ role, permission });
    else apply(role, permission, wanted);
  };

  const columns = matrix.roles;
  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <p className="flex items-start gap-2 text-footnote text-fg-muted">
        <Lock aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        {t('roles_locked')}
      </p>
      <Notice message={notice} />
      <FormError message={error} />
      <Card padded={false} className="overflow-x-auto">
        <table aria-label={t('roles_matrixLabel')} className="w-full min-w-[36rem] border-separate border-spacing-0 text-left">
          <thead>
            <tr>
              <th scope="col" className="sticky left-0 z-10 border-b border-line bg-surface px-3 py-3 text-footnote font-bold text-fg-muted">
                {t('roles_permission')}
              </th>
              {columns.map((role) => (
                <th key={role.key} scope="col" className="border-b border-line bg-surface px-1 py-3 text-center text-footnote font-bold text-fg">
                  <span className="inline-flex flex-col items-center gap-0.5">
                    {role.label || roleLabel(role.key)}
                    {(!role.editable || role.key === SUPERADMIN) && (
                      <span className="inline-flex items-center gap-1 text-caption font-normal text-fg-muted">
                        <Lock aria-hidden="true" className="size-3" />
                        {t('roles_lockedBadge')}
                      </span>
                    )}
                  </span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {groups.map(({ group, rows }) => (
              <Fragment key={group || 'other'}>
                <tr>
                  <th scope="rowgroup" colSpan={columns.length + 1} className="bg-surface-2 px-3 py-2 text-footnote font-bold text-fg-muted">
                    {group || t('roles_group_other')}
                  </th>
                </tr>
                {rows.map((permission) => (
                  <tr key={permission.key}>
                    <th scope="row" className="sticky left-0 z-10 w-44 border-b border-line bg-surface px-3 py-1 text-left text-footnote font-medium text-fg sm:w-64">
                      {permission.label}
                    </th>
                    {columns.map((role) => (
                      <td key={role.key} className="border-b border-line px-1 py-0.5 text-center">
                        <Switch
                          checked={granted(role, permission)}
                          disabled={!role.editable || role.key === SUPERADMIN || save.isPending}
                          label={t('roles_switchLabel', { role: role.label || roleLabel(role.key), permission: permission.label })}
                          onChange={(wanted) => toggle(role, permission, wanted)}
                        />
                      </td>
                    ))}
                  </tr>
                ))}
              </Fragment>
            ))}
          </tbody>
        </table>
      </Card>

      {asking && (
        <ConfirmDialog
          title={t('roles_riskyTitle', { permission: asking.permission.label })}
          text={t('roles_riskyText', { role: asking.role.label || roleLabel(asking.role.key) })}
          confirmLabel={t('roles_riskyConfirm')}
          cancelLabel={t('cancel')}
          closeLabel={t('close')}
          busy={save.isPending}
          onClose={() => setAsking(null)}
          onConfirm={() => apply(asking.role, asking.permission, true)}
        />
      )}
    </div>
  );
}

'use client';

import { useState } from 'react';
import { ShieldAlert, ShieldCheck, Undo2 } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, InlineError, PageHeader, FormError, Notice, Pill, Switch, Tabs } from '@rizqim/ui';
import { overrideFor, PERMISSION_ROLES, permissionState, useRolePermissions, useSaveRolePermission, type PermissionRole, type RolePermissionChange, type RolePermissionTable } from '@rizqim/staff';
import { roleLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { isRisky, permissionWords, sectionsOf, type ListedPermission } from './catalog';

// /permissions (permissions.manage, the owner): what each role may do alone in the store. Every switch is one
// PUT /owner/permissions that is saved at once and audited by the server; a switch that is back at the default sends null,
// which removes the store's override. Taking a risky permission (money out, cancelling) asks first.
export function PermissionsView() {
  const { t } = useI18n();
  const access = useCan();
  const allowed = access.ready && access.can('permissions.manage');
  const table = useRolePermissions({ enabled: allowed });

  if (access.ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_permissions')} />
        <EmptyState icon={ShieldAlert} title={t('mgmt_forbidden')} text={t('perm_ownerOnly')} />
      </>
    );
  }

  return (
    <>
      <PageHeader title={t('nav_permissions')} subtitle={t('perm_subtitle')} />
      {table.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(table.error, t)} retryLabel={t('retry')} retrying={table.isFetching} onRetry={() => void table.refetch()} />
      ) : !table.isSuccess ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : (
        <RoleChecklists table={table.data} />
      )}
    </>
  );
}

function RoleChecklists({ table }: { table: RolePermissionTable }) {
  const { t } = useI18n();
  const save = useSaveRolePermission();
  const editable = new Set<string>(table.roles.filter((role) => role.editable).map((role) => role.role));
  const roles = PERMISSION_ROLES.filter((role) => editable.has(role));
  const [role, setRole] = useState<PermissionRole>(roles[0] ?? 'manager');
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [asking, setAsking] = useState<{ permission: ListedPermission; name: string } | null>(null);

  if (roles.length === 0) return <EmptyState icon={ShieldCheck} title={t('perm_empty')} text={t('perm_emptyText')} />;

  const current = roles.includes(role) ? role : roles[0]!;
  const roleName = roleLabel(t, current);
  const sections = sectionsOf(table.permissions, (permission) => permission.grantable || permissionState(table, current, permission.key).allowed);
  const rows = sections.flatMap((section) => section.rows);
  const states = rows.map((permission) => permissionState(table, current, permission.key));
  const allowedCount = states.filter((state) => state.allowed).length;
  const changedCount = states.filter((state) => state.overridden).length;

  const apply = (permission: ListedPermission, name: string, wanted: boolean) => {
    const state = permissionState(table, current, permission.key);
    const reset = wanted === state.defaultAllowed;
    setNotice(null);
    setError(null);
    save.mutate(
      { role: current, permission: permission.key as RolePermissionChange['permission'], allowed: overrideFor(state.defaultAllowed, wanted) },
      {
        onSuccess: () => {
          setAsking(null);
          setNotice(t(reset ? 'perm_savedReset' : wanted ? 'perm_savedGranted' : 'perm_savedRevoked', { role: roleName, name }));
        },
        onError: (failure) => {
          setAsking(null);
          setError(errorText(failure, t));
        },
      },
    );
  };

  const toggle = (permission: ListedPermission, name: string, wanted: boolean) => {
    if (wanted && isRisky(permission.key)) setAsking({ permission, name });
    else apply(permission, name, wanted);
  };

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('perm_intro')}</p>
      <Tabs idPrefix="perm" label={t('perm_roles')} value={current} onChange={(next) => { setRole(next); setNotice(null); setError(null); }} tabs={roles.map((id) => ({ id, label: roleLabel(t, id) }))} />
      <div role="tabpanel" id={`perm-panel-${current}`} aria-labelledby={`perm-tab-${current}`} className="flex flex-col gap-4">
        <Notice message={notice} />
        <FormError message={error} />
        <p className="text-footnote text-fg-muted">{changedCount === 0 ? t('perm_summaryNone', { allowed: allowedCount }) : t('perm_summary', { allowed: allowedCount, changed: changedCount })}</p>
        {sections.map((section) => (
          <Card key={section.id} className="flex flex-col gap-1">
            <h2 className="text-headline font-bold text-fg">{t(`perm_group_${section.id}`)}</h2>
            <ul className="flex flex-col divide-y divide-line">
              {section.rows.map((permission) => (
                <PermissionRow
                  key={permission.key}
                  permission={permission}
                  state={permissionState(table, current, permission.key)}
                  busy={save.isPending}
                  onToggle={(name, wanted) => toggle(permission, name, wanted)}
                />
              ))}
            </ul>
          </Card>
        ))}
      </div>
      {asking && (
        <ConfirmDialog
          title={t('perm_confirmTitle', { name: asking.name })}
          text={t('perm_confirmText', { role: roleName })}
          confirmLabel={t('perm_confirmGrant')}
          busy={save.isPending}
          onConfirm={() => apply(asking.permission, asking.name, true)}
          onClose={() => setAsking(null)}
        />
      )}
    </div>
  );
}

function PermissionRow({
  permission,
  state,
  busy,
  onToggle,
}: {
  permission: ListedPermission;
  state: ReturnType<typeof permissionState>;
  busy: boolean;
  onToggle: (name: string, wanted: boolean) => void;
}) {
  const { t } = useI18n();
  const words = permissionWords(t, permission.key, permission.label);
  const risky = isRisky(permission.key);
  // A permission the owner keeps cannot be given: its switch only turns off
  const locked = !permission.grantable && !state.allowed;
  return (
    <li className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
      <Switch checked={state.allowed} onChange={(wanted) => onToggle(words.label, wanted)} label={words.label} disabled={busy || locked} />
      <div className="flex min-w-0 flex-1 basis-60 flex-col">
        <span className="flex flex-wrap items-center gap-2 text-body font-medium text-fg">
          {words.label}
          {risky && (
            <Pill tone="warning" icon={ShieldAlert}>
              {t('perm_risky')}
            </Pill>
          )}
          {state.overridden && <Pill tone="info">{t('perm_changed')}</Pill>}
        </span>
        {words.hint && <span className="text-footnote text-fg-muted">{words.hint}</span>}
        {!permission.grantable && <span className="text-footnote text-fg-muted">{t('perm_ownerKeeps')}</span>}
        {state.overridden && <span className="text-footnote text-fg-muted">{t('perm_default', { value: t(state.defaultAllowed ? 'perm_defaultOn' : 'perm_defaultOff') })}</span>}
      </div>
      {state.overridden && (
        <Button variant="secondary" icon={Undo2} disabled={busy} aria-label={t('perm_resetLabel', { name: words.label })} onClick={() => onToggle(words.label, state.defaultAllowed)}>
          {t('perm_reset')}
        </Button>
      )}
    </li>
  );
}
